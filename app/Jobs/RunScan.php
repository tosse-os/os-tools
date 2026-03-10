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
use RuntimeException;
use Symfony\Component\Process\Process;

class RunScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $scanId;
    public array $checks;
    public bool $multiScan;

    public function __construct(string $scanId, array $checks = [], bool $multiScan = false)
    {
        $this->scanId = $scanId;
        $this->checks = $checks;
        $this->multiScan = $multiScan;
    }

    public function handle(ReportPersistenceService $reportPersistenceService): void
    {
        $report = Report::find($this->scanId);
        $scan = Scan::find($this->scanId);

        if ($this->multiScan) {
            if ($report) {
                $this->runMultiScanForReport($report, $scan, $reportPersistenceService);
                return;
            }

            if ($scan) {
                $this->runMultiScan($scan, $reportPersistenceService);
                return;
            }
        }

        if ($report) {
            $this->runCrawlerReportScan($report, $reportPersistenceService);
            return;
        }

        if ($scan) {
            $this->runMultiScan($scan, $reportPersistenceService);
            return;
        }

        throw new RuntimeException('RunScan could not find Report or Scan for ID: '.$this->scanId);
    }

    private function runCrawlerReportScan(Report $report, ReportPersistenceService $reportPersistenceService): void
    {
        $report->update(['status' => 'processing', 'started_at' => now()]);

        $options = [
            'scan_id' => $report->id,
            'url' => $report->url,
            'checks' => array_values(array_unique(array_merge($this->checks, ['status']))),
            'max_pages' => config('seo.max_pages', 20),
            'max_depth' => config('seo.max_depth', 2),
            'page_timeout' => config('seo.page_timeout', 30),
            'max_retries' => config('seo.max_retries', 3),
            'retry_delay' => config('seo.retry_delay', 10),
            'max_scan_time' => config('seo.max_scan_time', 300),
        ];

        $command = sprintf(
            'node %s %s',
            escapeshellarg(base_path('node-scanner/core/scanner.js')),
            escapeshellarg(json_encode($options, JSON_UNESCAPED_SLASHES))
        );

        $process = Process::fromShellCommandline($command, base_path());
        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            $report->update(['status' => 'failed', 'finished_at' => now()]);
            return;
        }

        $payload = json_decode($process->getOutput(), true);
        $firstResult = is_array($payload) && isset($payload[0]) && is_array($payload[0]) ? $payload[0] : null;

        if (!$firstResult) {
            $report->update(['status' => 'failed', 'finished_at' => now()]);
            return;
        }

        $directory = "scans/{$report->id}";
        Storage::makeDirectory($directory);
        Storage::put("{$directory}/pages.json", json_encode([$firstResult], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Storage::put("{$directory}/progress.json", json_encode([
            'current' => 1,
            'total' => 1,
            'status' => 'done',
        ]));

        $reportPersistenceService->syncFromStorage($report->fresh());
    }

    private function runMultiScanForReport(Report $report, ?Scan $scan, ReportPersistenceService $reportPersistenceService): void
    {
        $report->update(['status' => 'processing', 'started_at' => now()]);
        if ($scan) {
            $scan->update(['status' => 'running']);
        }

        $options = [
            'scan_id' => $report->id,
            'url' => $report->url,
            'checks' => $this->checks,
            'max_pages' => config('seo.max_pages', 20),
            'max_depth' => config('seo.max_depth', 2),
            'page_timeout' => config('seo.page_timeout', 30),
            'max_parallel_pages' => (int) env('SCAN_CONCURRENCY', config('seo.max_parallel_pages', 3)),
            'max_retries' => config('seo.max_retries', 3),
            'retry_delay' => config('seo.retry_delay', 10),
            'max_scan_time' => config('seo.max_scan_time', 300),
        ];

        $command = sprintf(
            'node %s %s %s',
            escapeshellarg(base_path('node-scanner/core/multiScanner.js')),
            escapeshellarg(json_encode($options, JSON_UNESCAPED_SLASHES)),
            escapeshellarg($report->id)
        );

        $process = Process::fromShellCommandline($command, base_path());
        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            $report->update(['status' => 'failed', 'finished_at' => now()]);
            if ($scan) {
                $scan->update(['status' => 'failed']);
            }
            return;
        }

        $reportPersistenceService->syncFromStorage($report->fresh());

        if ($scan) {
            $scan->update(['status' => 'done']);
        }
    }

    private function runMultiScan(Scan $scan, ReportPersistenceService $reportPersistenceService): void
    {
        $scan->update(['status' => 'running']);

        $options = [
            'scan_id' => $scan->id,
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

        $command = sprintf(
            'node %s %s %s',
            escapeshellarg(base_path('node-scanner/core/multiScanner.js')),
            escapeshellarg(json_encode($options, JSON_UNESCAPED_SLASHES)),
            escapeshellarg($this->scanId)
        );

        $process = Process::fromShellCommandline($command, base_path());
        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            $scan->update(['status' => 'failed']);
            return;
        }

        $report = Report::firstOrCreate(
            ['id' => $scan->id],
            ['url' => $scan->url, 'type' => 'crawler', 'status' => 'processing']
        );

        $reportPersistenceService->syncFromStorage($report);
        $scan->update(['status' => 'done']);
    }
}
