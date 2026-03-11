<?php

namespace App\Console\Commands;

use App\Services\Crawler\CrawlerEventConsumer;
use Illuminate\Console\Command;

class ConsumeCrawlEvents extends Command
{
    protected $signature = 'crawl:consume-events {--once} {--timeout=5}';
    protected $description = 'Consume crawl events from Redis queue crawl:event_queue.';

    public function handle(CrawlerEventConsumer $consumer): int
    {
        $once = (bool) $this->option('once');
        $timeout = (int) $this->option('timeout');

        do {
            $consumer->consumeOnce($timeout);
        } while (!$once);

        return self::SUCCESS;
    }
}
