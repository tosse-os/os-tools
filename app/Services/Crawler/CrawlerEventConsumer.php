<?php

namespace App\Services\Crawler;

use Illuminate\Support\Facades\Redis;

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

        return $this->crawlerEventProcessor->process($crawlId, $decoded);
    }
}
