<?php

namespace App\Jobs;

use App\Models\Scan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class RunScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $scanId;
    public array $checks;

    public function __construct(string $scanId, array $checks = [])
    {
        $this->scanId = $scanId;
        $this->checks = $checks;
    }

    public function handle(): void
    {
        Log::info('Multi Scan gestartet', [
            'scan_id' => $this->scanId,
            'checks' => $this->checks
        ]);

        $scan = Scan::findOrFail($this->scanId);

        $scan->update(['status' => 'running']);

        $options = [
            'url' => $scan->url,
            'checks' => $this->checks,
            'maxPages' => config('tools.limits.max_urls_per_scan', 6),
        ];

        $process = new Process([
            'node',
            base_path('node-scanner/multiScanner.js'),
            json_encode($options),
            $this->scanId
        ]);

        $process->start();
    }
}
