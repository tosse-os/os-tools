<?php

namespace App\Services\Crawler;

class CrawlMetricsService
{
    public function normalizePageMetrics(array $payload): array
    {
        return [
            'url' => $payload['url'] ?? null,
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
        ];
    }
}
