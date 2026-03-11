<?php

namespace App\Jobs;

use App\Models\Crawl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class RunCrawlPipeline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly string $crawlId)
    {
    }

    public function handle(): void
    {
        $crawl = Crawl::findOrFail($this->crawlId);

        Redis::lpush('crawl:event_queue', json_encode([
            'crawl_id' => $crawl->id,
            'type' => 'crawl_started',
            'timestamp' => now()->toIso8601String(),
            'payload' => [
                'url' => $crawl->root_url ?: $crawl->start_url,
            ],
        ], JSON_UNESCAPED_SLASHES));

        Redis::lpush('crawl:event_queue', json_encode([
            'crawl_id' => $crawl->id,
            'type' => 'url_discovered',
            'timestamp' => now()->toIso8601String(),
            'payload' => [
                'url' => $crawl->root_url ?: $crawl->start_url,
                'depth' => 0,
            ],
        ], JSON_UNESCAPED_SLASHES));
    }
}
