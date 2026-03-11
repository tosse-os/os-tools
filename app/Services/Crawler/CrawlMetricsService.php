<?php

namespace App\Services\Crawler;

class CrawlMetricsService
{
    public function normalizePagePayload(array $payload): array
    {
        $content = (string) ($payload['content'] ?? '');
        $text = (string) ($payload['text_content'] ?? '');

        $payload['content_hash'] = $payload['content_hash'] ?? sha1($content);
        $payload['text_hash'] = $payload['text_hash'] ?? sha1($text);
        $payload['word_count'] = $payload['word_count'] ?? str_word_count($text);

        return $payload;
    }
}
