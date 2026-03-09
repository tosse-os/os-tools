<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\Scan;
use App\Services\ReportPersistenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

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

    public function handle(ReportPersistenceService $reportPersistenceService): void
    {
        Log::info('RunScan gestartet', [
            'scan_id' => $this->scanId,
            'checks' => $this->checks,
        ]);

        $report = Report::find($this->scanId);
        $scan = $report ? null : Scan::find($this->scanId);

        if ($report) {
            $this->runCrawlerReportScan($report, $reportPersistenceService);
            return;
        }

        if ($scan) {
            $this->runMultiScan($scan);
            return;
        }

        Log::error('RunScan: Weder Report noch Scan gefunden', ['scan_id' => $this->scanId]);
    }

    private function runCrawlerReportScan(Report $report, ReportPersistenceService $reportPersistenceService): void
    {
        $report->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $options = [
            'url' => $report->url,
            'checks' => array_values(array_unique(array_merge($this->checks, ['status']))),
            'max_pages' => config('seo.max_pages', 20),
            'max_depth' => config('seo.max_depth', 2),
            'page_timeout' => config('seo.page_timeout', 30),
            'max_retries' => config('seo.max_retries', 3),
            'retry_delay' => config('seo.retry_delay', 10),
            'max_scan_time' => config('seo.max_scan_time', 300),
        ];

        $process = new Process([
            'node',
            base_path('node-scanner/core/scanner.js'),
            json_encode($options),
        ]);

        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Crawler Report Scan fehlgeschlagen', [
                'report_id' => $report->id,
                'error' => $process->getErrorOutput(),
            ]);

            $report->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);

            return;
        }

        $payload = json_decode($process->getOutput(), true);
        $firstResult = is_array($payload) && isset($payload[0]) && is_array($payload[0]) ? $payload[0] : null;

        if (!$firstResult) {
            Log::error('Crawler Report Scan lieferte kein Ergebnis', ['report_id' => $report->id]);
            $report->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);
            return;
        }

        $directory = "scans/{$report->id}";
        Storage::makeDirectory($directory);
        Storage::put("{$directory}/0.json", json_encode($firstResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Storage::put("{$directory}/progress.json", json_encode([
            'current' => 1,
            'total' => 1,
            'status' => 'done',
        ]));

        $reportPersistenceService->syncFromStorage($report->fresh());
    }

    private function runMultiScan(Scan $scan): void
    {
        $scan->update(['status' => 'running']);

        $options = [
            'url' => $scan->url,
            'checks' => $this->checks,
            'max_pages' => config('seo.max_pages', 20),
            'max_depth' => config('seo.max_depth', 2),
            'page_timeout' => config('seo.page_timeout', 30),
            'max_parallel_pages' => (int) env('SCAN_CONCURRENCY', config('seo.max_parallel_pages', 3)),
            'max_retries' => config('seo.max_retries', 3),
            'retry_delay' => config('seo.retry_delay', 10),
            'max_scan_time' => config('seo.max_scan_time', 300),
        ];

        $process = new Process([
            'node',
            base_path('node-scanner/core/multiScanner.js'),
            json_encode($options),
            $this->scanId,
        ]);

        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('RunScan Node Prozess fehlgeschlagen', [
                'scan_id' => $this->scanId,
                'error' => $process->getErrorOutput(),
            ]);

            $scan->update(['status' => 'failed']);
            return;
        }

        $scan->update(['status' => 'done']);
    }
}
