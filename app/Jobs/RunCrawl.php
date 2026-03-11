<?php

namespace App\Jobs;

use App\Models\Crawl;
use App\Models\Report;
use App\Services\Crawler\CrawlerEventConsumer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;

class RunCrawl implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly string $crawlId)
    {
    }

    public function handle(CrawlerEventConsumer $consumer): void
    {
        $crawl = Crawl::findOrFail($this->crawlId);
        $rootUrl = $crawl->entry_url;

        Report::whereKey($this->crawlId)->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        Crawl::whereKey($this->crawlId)->update([
            'status' => 'running',
            'started_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('crawl_queue')->updateOrInsert(
            ['crawl_id' => $crawl->id, 'url' => $rootUrl],
            ['depth' => 0, 'status' => 'pending', 'created_at' => now()]
        );

        Redis::lpush('crawl:url_queue', json_encode([
            'crawl_id' => $crawl->id,
            'url' => $rootUrl,
            'depth' => 0,
        ], JSON_UNESCAPED_SLASHES));

        $workers = [];
        $workerCount = max(1, (int) env('CRAWLER_WORKERS', 2));
        for ($i = 0; $i < $workerCount; $i++) {
            $process = new Process([
                'node',
                base_path('node-scanner/core/crawler-worker.js'),
            ], base_path(), [
                'CRAWLER_WORKER_ID' => (string) ($i + 1),
                'CRAWLER_CONCURRENCY' => (string) env('CRAWLER_CONCURRENCY', 4),
            ]);

            $process->setTimeout(null);
            $process->start();
            $workers[] = $process;
        }

        Redis::lpush('crawl:event_queue', json_encode([
            'crawl_id' => $crawl->id,
            'type' => 'crawl_started',
            'timestamp' => now()->toIso8601String(),
            'payload' => ['url' => $rootUrl],
        ], JSON_UNESCAPED_SLASHES));

        $idleCycles = 0;
        while ($idleCycles < 30) {
            $consumed = $consumer->consume($crawl->id, 1);
            if ($consumed) {
                $idleCycles = 0;
                continue;
            }

            $idleCycles++;
            $pending = DB::table('crawl_queue')->where('crawl_id', $crawl->id)->where('status', 'pending')->exists();
            if (!$pending && $idleCycles > 5) {
                break;
            }
        }

        for ($i = 0; $i < $workerCount; $i++) {
            Redis::lpush('crawl:url_queue', json_encode([
                'type' => 'crawl_stop',
                'crawl_id' => $crawl->id,
            ], JSON_UNESCAPED_SLASHES));
        }

        Redis::lpush('crawl:event_queue', json_encode([
            'crawl_id' => $crawl->id,
            'type' => 'crawl_finished',
            'timestamp' => now()->toIso8601String(),
            'payload' => [],
        ], JSON_UNESCAPED_SLASHES));

        $consumer->consume($crawl->id, 1);

        foreach ($workers as $worker) {
            $worker->stop(1);
        }

        Report::whereKey($this->crawlId)->update([
            'status' => 'done',
            'finished_at' => now(),
        ]);
    }
}
