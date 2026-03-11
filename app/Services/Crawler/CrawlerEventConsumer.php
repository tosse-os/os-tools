<?php

namespace App\Services\Crawler;

use App\Models\Crawl;
use App\Models\CrawlEvent;
use App\Models\CrawlPage;
use App\Models\CrawlQueue;
use Illuminate\Support\Facades\Redis;

class CrawlerEventConsumer
{
    public function __construct(
        private readonly CrawlPagePersister $crawlPagePersister,
        private readonly CrawlLinkPersister $crawlLinkPersister,
        private readonly CrawlMetricsService $crawlMetricsService,
        private readonly CrawlProgressService $crawlProgressService,
    ) {
    }

    public function consumeOnce(int $timeout = 5): bool
    {
        $message = Redis::brpop(['crawl:event_queue'], $timeout);
        if (!is_array($message) || !isset($message[1])) {
            return false;
        }

        $event = json_decode($message[1], true);
        if (!is_array($event)) {
            return false;
        }

        $crawlId = (string) ($event['crawl_id'] ?? '');
        $type = (string) ($event['type'] ?? '');
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];

        if ($crawlId === '' || $type === '') {
            return false;
        }

        CrawlEvent::create([
            'crawl_id' => $crawlId,
            'type' => $type,
            'payload' => $payload,
            'created_at' => now(),
        ]);

        match ($type) {
            'crawl_started' => $this->handleStarted($crawlId),
            'url_discovered' => $this->handleUrlDiscovered($crawlId, $payload),
            'page_crawled' => $this->handlePageCrawled($crawlId, $payload),
            'link_discovered' => $this->crawlLinkPersister->persist($crawlId, $payload),
            'crawl_progress' => $this->handleProgress($crawlId, $payload),
            'crawl_finished' => $this->handleFinished($crawlId, $payload),
            default => null,
        };

        return true;
    }

    private function handleStarted(string $crawlId): void
    {
        Crawl::whereKey($crawlId)->update([
            'status' => 'running',
            'started_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function handleUrlDiscovered(string $crawlId, array $payload): void
    {
        $url = (string) ($payload['url'] ?? '');
        if ($url === '') {
            return;
        }

        if (CrawlPage::where('crawl_id', $crawlId)->where('url', $url)->exists()) {
            return;
        }

        $inserted = CrawlQueue::query()->insertOrIgnore([
            'crawl_id' => $crawlId,
            'url' => $url,
            'depth' => (int) ($payload['depth'] ?? 0),
            'status' => 'queued',
            'created_at' => now(),
        ]);

        if ($inserted) {
            $this->crawlProgressService->incrementDiscovered($crawlId);
            Redis::lpush('crawl:url_queue', json_encode([
                'crawl_id' => $crawlId,
                'url' => $url,
                'depth' => (int) ($payload['depth'] ?? 0),
            ], JSON_UNESCAPED_SLASHES));
        }
    }

    private function handlePageCrawled(string $crawlId, array $payload): void
    {
        $metrics = $this->crawlMetricsService->normalizePageMetrics($payload);
        $this->crawlPagePersister->persist($crawlId, $metrics);

        CrawlQueue::where('crawl_id', $crawlId)
            ->where('url', $payload['url'] ?? '')
            ->update(['status' => 'done']);

        $this->crawlProgressService->incrementScanned($crawlId);

        if (($payload['status_code'] ?? 200) >= 400) {
            $this->crawlProgressService->incrementFailed($crawlId);
        }
    }

    private function handleProgress(string $crawlId, array $payload): void
    {
        Crawl::whereKey($crawlId)->update([
            'status' => $payload['status'] ?? 'running',
            'updated_at' => now(),
        ]);
    }

    private function handleFinished(string $crawlId, array $payload): void
    {
        Crawl::whereKey($crawlId)->update([
            'status' => $payload['status'] ?? 'done',
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
