<?php

namespace App\Jobs;

use App\Models\Crawl;
use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class RunCrawl implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly string $crawlId)
    {
    }

    public function handle(): void
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

        $options = [
            'scan_id' => $crawl->id,
            'url' => $rootUrl,
            'max_pages' => (int) env('CRAWLER_MAX_PAGES', 20),
            'max_depth' => (int) env('CRAWLER_MAX_DEPTH', 2),
            'page_timeout' => (int) env('CRAWLER_PAGE_TIMEOUT', 30),
            'max_retries' => (int) env('CRAWLER_MAX_RETRIES', 2),
            'retry_delay' => (int) env('CRAWLER_RETRY_DELAY', 2),
            'concurrency' => (int) env('CRAWLER_CONCURRENCY', 4),
            'checks' => ['heading', 'alt', 'status'],
        ];

        $process = new Process([
            'node',
            base_path('node-scanner/core/crawler.js'),
            json_encode($options, JSON_UNESCAPED_SLASHES),
        ]);

        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Crawl Node Prozess fehlgeschlagen', [
                'crawl_id' => $this->crawlId,
                'error' => $process->getErrorOutput(),
            ]);

            Report::whereKey($this->crawlId)->update([
                'status' => 'aborted',
                'finished_at' => now(),
            ]);

            Crawl::whereKey($this->crawlId)->update([
                'status' => 'aborted',
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        Report::whereKey($this->crawlId)->update([
            'status' => 'done',
            'finished_at' => now(),
        ]);

        Crawl::whereKey($this->crawlId)->update([
            'status' => 'done',
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
