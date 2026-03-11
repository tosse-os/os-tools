<?php

namespace App\Services\Crawler;

use Illuminate\Support\Facades\DB;

class CrawlLinkPersister
{
    public function persist(string $crawlId, array $payload): void
    {
        DB::table('crawl_links')->updateOrInsert(
            [
                'crawl_id' => $crawlId,
                'source_url' => $payload['source_url'],
                'target_url' => $payload['target_url'],
            ],
            [
                'type' => $payload['type'] ?? 'internal',
                'status_code' => $payload['status_code'] ?? null,
                'redirect_chain_length' => (int) ($payload['redirect_chain_length'] ?? 0),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
