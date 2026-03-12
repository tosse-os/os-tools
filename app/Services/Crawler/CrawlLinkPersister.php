<?php

namespace App\Services\Crawler;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CrawlLinkPersister
{
    public function persist(string $crawlId, array $payload): void
    {
        $sourceUrl = $payload['source_url'] ?? $payload['source'] ?? null;
        $targetUrl = $payload['target_url'] ?? $payload['target'] ?? null;

        if (!$sourceUrl || !$targetUrl) {
            throw new InvalidArgumentException('CrawlLinkPersister requires source_url and target_url in payload.');
        }

        $type = $payload['type'] ?? $payload['link_type'] ?? 'internal';

        Log::info('persisting crawl link', [
            'crawl_id' => $crawlId,
            'source_url' => $sourceUrl,
            'target_url' => $targetUrl,
            'type' => $type,
        ]);

        DB::table('crawl_links')->updateOrInsert(
            [
                'crawl_id' => $crawlId,
                'source_url' => $sourceUrl,
                'target_url' => $targetUrl,
            ],
            [
                'type' => $type,
                'link_type' => $type,
                'status_code' => $payload['status_code'] ?? null,
                'redirect_chain_length' => (int) ($payload['redirect_chain_length'] ?? 0),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
