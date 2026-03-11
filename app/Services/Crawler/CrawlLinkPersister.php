<?php

namespace App\Services\Crawler;

use App\Models\CrawlLink;

class CrawlLinkPersister
{
    public function persist(string $crawlId, array $payload): void
    {
        $sourceUrl = (string) ($payload['source_url'] ?? '');
        $targetUrl = (string) ($payload['target_url'] ?? '');

        if ($sourceUrl === '' || $targetUrl === '') {
            return;
        }

        CrawlLink::updateOrInsert(
            [
                'crawl_id' => $crawlId,
                'source_url' => $sourceUrl,
                'target_url' => $targetUrl,
                'type' => $payload['type'] ?? 'internal',
            ],
            [
                'status_code' => $payload['status_code'] ?? null,
                'redirect_chain_length' => (int) ($payload['redirect_chain_length'] ?? 0),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
