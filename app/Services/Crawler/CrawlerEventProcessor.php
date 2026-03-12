<?php

namespace App\Services\Crawler;

use App\Models\Crawl;
use App\Support\CrawlerRuntime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class CrawlerEventProcessor
{
    public function __construct(
        private readonly CrawlPagePersister $crawlPagePersister,
        private readonly CrawlLinkPersister $crawlLinkPersister,
        private readonly CrawlMetricsService $crawlMetricsService,
        private readonly CrawlProgressService $crawlProgressService,
    ) {
    }

    public function process(string $crawlId, array $decoded): bool
    {
        if (($decoded['crawl_id'] ?? null) !== $crawlId) {
            return false;
        }

        $type = $decoded['type'] ?? 'unknown';
        $payload = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];

        DB::table('crawl_events')->insert([
            'crawl_id' => $crawlId,
            'type' => $type,
            'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
        ]);

        if ($type === 'url_discovered') {
            $this->handleUrlDiscovered($crawlId, $payload);
        }

        if ($type === 'page_crawled') {
            $normalizedPayload = $this->crawlMetricsService->normalizePagePayload($payload);
            $this->crawlPagePersister->persist($crawlId, $normalizedPayload);

            if (($normalizedPayload['status_code'] ?? 500) >= 400) {
                $this->crawlProgressService->incrementFailed($crawlId);
            } else {
                $this->crawlProgressService->incrementScanned($crawlId);
            }
        }

        if ($type === 'link_discovered') {
            $this->crawlLinkPersister->persist($crawlId, $payload);
        }

        if ($type === 'crawl_progress' && !empty($payload['url']) && !empty($payload['status'])) {
            DB::table('crawl_queue')
                ->where('crawl_id', $crawlId)
                ->where('url', $payload['url'])
                ->update(['status' => $payload['status']]);
        }

        if ($type === 'crawl_started') {
            Crawl::whereKey($crawlId)->update([
                'status' => 'running',
                'started_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($type === 'crawl_finished') {
            Crawl::whereKey($crawlId)->update([
                'status' => 'done',
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return true;
    }

    private function handleUrlDiscovered(string $crawlId, array $payload): void
    {
        $url = $payload['url'] ?? null;
        if (!$url) {
            return;
        }

        $exists = DB::table('crawl_pages')->where('crawl_id', $crawlId)->where('url', $url)->exists();
        if ($exists) {
            return;
        }

        $known = DB::table('crawl_queue')->where('crawl_id', $crawlId)->where('url', $url)->exists();

        DB::table('crawl_queue')->updateOrInsert(
            ['crawl_id' => $crawlId, 'url' => $url],
            ['depth' => (int) ($payload['depth'] ?? 0), 'status' => 'pending', 'created_at' => now()],
        );

        if (!$known) {
            $this->crawlProgressService->incrementDiscovered($crawlId);
        }

        if (CrawlerRuntime::useRedis()) {
            Redis::lpush('crawl:url_queue', json_encode([
                'crawl_id' => $crawlId,
                'url' => $url,
                'depth' => (int) ($payload['depth'] ?? 0),
            ], JSON_UNESCAPED_SLASHES));
        }
    }
}
