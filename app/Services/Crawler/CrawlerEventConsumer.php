<?php

namespace App\Services\Crawler;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class CrawlerEventConsumer
{
    public function __construct(
        private readonly CrawlerEventProcessor $crawlerEventProcessor,
    ) {
    }

    public function consume(string $crawlId, int $blockSeconds = 1): bool
    {
        $event = Redis::blpop('crawl:event_queue', $blockSeconds);
        if (!$event || !isset($event[1])) {
            return false;
        }

        $decoded = json_decode($event[1], true);
        if (!is_array($decoded)) {
            return false;
        }

        Log::info('crawler event received from redis queue', [
            'expected_crawl_id' => $crawlId,
            'event_type' => $decoded['type'] ?? 'unknown',
            'event_crawl_id' => $decoded['crawl_id'] ?? null,
            'payload' => $decoded['payload'] ?? null,
        ]);

        return $this->crawlerEventProcessor->process($crawlId, $decoded);
    }
}
