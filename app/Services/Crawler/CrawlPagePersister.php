<?php

namespace App\Services\Crawler;

use Illuminate\Support\Facades\DB;

class CrawlPagePersister
{
    public function persist(string $crawlId, array $payload): void
    {
        DB::table('crawl_pages')->upsert([
            [
                'crawl_id' => $crawlId,
                'url' => $payload['url'],
                'status_code' => $payload['status_code'] ?? null,
                'depth' => (int) ($payload['depth'] ?? 0),
                'title' => $payload['title'] ?? null,
                'meta_description' => $payload['meta_description'] ?? null,
                'h1_count' => (int) ($payload['h1_count'] ?? 0),
                'alt_missing_count' => (int) ($payload['alt_missing_count'] ?? 0),
                'internal_links_count' => (int) ($payload['internal_links_count'] ?? 0),
                'external_links_count' => (int) ($payload['external_links_count'] ?? 0),
                'word_count' => (int) ($payload['word_count'] ?? 0),
                'content_hash' => $payload['content_hash'] ?? null,
                'text_hash' => $payload['text_hash'] ?? null,
                'response_time' => $payload['response_time'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        ], ['crawl_id', 'url'], [
            'status_code',
            'depth',
            'title',
            'meta_description',
            'h1_count',
            'alt_missing_count',
            'internal_links_count',
            'external_links_count',
            'word_count',
            'content_hash',
            'text_hash',
            'response_time',
            'updated_at',
        ]);
    }
}
