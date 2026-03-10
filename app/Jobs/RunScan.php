<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\Scan;
use App\Services\IssueDetectionService;
use App\Services\ReportPersistenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class RunScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $scanId;
    public array $checks;
    public bool $multiScan = false;

    public function __construct(string $scanId, array $checks = [], bool $multiScan = false)
    {
        $this->scanId = $scanId;
        $this->checks = $checks;
        $this->multiScan = $multiScan;
    }

    public function handle(ReportPersistenceService $reportPersistenceService): void
    {
        Log::debug('[SCAN TRACE] job_started', [
            'scan_id' => $this->scanId,
            'checks' => $this->checks,
        ]);

        Log::info('RunScan gestartet', [
            'scan_id' => $this->scanId,
            'checks' => $this->checks,
        ]);

        $report = Report::find($this->scanId);
        $scan = $report ? null : Scan::find($this->scanId);

        Log::debug('[SCAN TRACE] model_lookup', [
            'scan_id' => $this->scanId,
            'report_found' => (bool) $report,
            'scan_found' => (bool) $scan,
        ]);

        if ($report) {
            Log::debug('[SCAN TRACE] executing_report_scan', [
                'scan_id' => $this->scanId,
            ]);
            $this->runCrawlerReportScan($report, $reportPersistenceService);
        } elseif ($scan) {
            Log::debug('[SCAN TRACE] executing_multi_scan', [
                'scan_id' => $this->scanId,
            ]);
            $this->runMultiScan($scan, $reportPersistenceService);
        } else {
            Log::critical('[SCAN TRACE] lookup_failed', [
                'scan_id' => $this->scanId,
            ]);

            throw new RuntimeException('RunScan could not find Report or Scan for ID: '.$this->scanId);
        }

        Log::debug('[SCAN TRACE] job_completed', [
            'scan_id' => $this->scanId,
        ]);
    }

    private function runCrawlerReportScan(Report $report, ReportPersistenceService $reportPersistenceService): void
    {
        Log::info('[SCAN] Job gestartet', [
            'scan_id' => $report->id,
            'url' => $report->url,
        ]);

        $report->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

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
        Log::debug('[SCAN TRACE] node_process_start', [
            'scan_id' => $report->id,
            'target' => 'scanner.js',
        ]);
        Log::info('[SCAN] Starting Node Scanner', ['scan_id' => $report->id]);
        Log::debug('[SCAN TRACE] node_command', [
            'scan_id' => $report->id,
            'command' => $process->getCommandLine(),
        ]);
        Log::debug('[SCAN] Node command', ['command' => $process->getCommandLine()]);
        try {
            $process->run(function (string $type, string $buffer) use ($report): void {
                if ($type === Process::ERR) {
                    Log::error('[NODE STDERR]', [
                        'scan_id' => $report->id,
                        'output' => trim($buffer),
                    ]);

                    return;
                }

                Log::debug('[NODE STDOUT]', [
                    'scan_id' => $report->id,
                    'output' => trim($buffer),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('[SCAN TRACE] node_process_exception', [
                'scan_id' => $report->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
        Log::debug('[SCAN TRACE] node_process_finished', [
            'scan_id' => $report->id,
            'exit_code' => $process->getExitCode(),
            'exit_code_text' => $process->getExitCodeText(),
            'successful' => $process->isSuccessful(),
        ]);
        Log::info('[SCAN] Node Scanner finished', ['scan_id' => $report->id]);

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
        $pagesCrawled = (int) ($firstResult['pages_crawled'] ?? 1);
        if ($pagesCrawled < 1) {
            $pagesCrawled = 1;
        }

        Storage::put("{$directory}/progress.json", json_encode([
            'current' => $pagesCrawled,
            'total' => $pagesCrawled,
            'status' => 'done',
        ]));

        $reportPersistenceService->syncFromStorage($report->fresh());
    }

    private function persistScanEvent(string $scanId, array $payload): void
    {
        $eventType = $payload['type'] ?? null;
        if (!in_array($eventType, ['crawl_progress', 'page_scanned'], true)) {
            return;
        }

        $directory = storage_path("scans/{$scanId}");
        if (!File::exists($directory)) {
            File::ensureDirectoryExists($directory);
        }

        if ($eventType === 'crawl_progress') {
            $progressPayload = [
                'type' => 'crawl_progress',
                'status' => $payload['status'] ?? 'running',
                'stage' => $payload['stage'] ?? 'scanning',
                'current' => (int) ($payload['scanned_pages'] ?? 0),
                'total' => (int) ($payload['total'] ?? config('seo.max_pages', 20)),
                'scanned_pages' => (int) ($payload['scanned_pages'] ?? 0),
                'queue_size' => (int) ($payload['queue_size'] ?? 0),
                'current_url' => $payload['current_url'] ?? null,
            ];

            File::put($directory.'/progress.json', json_encode($progressPayload));

            return;
        }

        $eventPayload = [
            'type' => 'page_scanned',
            'url' => $payload['url'] ?? null,
            'status' => $payload['status'] ?? null,
            'alt_count' => (int) ($payload['alt_count'] ?? 0),
            'heading_count' => (int) ($payload['heading_count'] ?? 0),
            'error' => $payload['error'] ?? null,
        ];

        File::append($directory.'/events.jsonl', json_encode($eventPayload).PHP_EOL);
    }

    private function runMultiScan(Scan $scan, ReportPersistenceService $reportPersistenceService): void
    {
        Log::info('[SCAN] Job gestartet', [
            'scan_id' => $scan->id,
            'url' => $scan->url,
        ]);

        $scan->update(['status' => 'running']);

        Storage::makeDirectory("scans/{$scan->id}");
        Storage::put("scans/{$scan->id}/progress.json", json_encode([
            'type' => 'crawl_progress',
            'status' => 'running',
            'stage' => 'crawling',
            'current' => 0,
            'total' => (int) config('seo.max_pages', 20),
            'scanned_pages' => 0,
            'queue_size' => 0,
            'current_url' => $scan->url,
        ]));

        $options = [
            'scan_id' => $scan->id,
            'url' => $scan->url,
            'checks' => $this->checks,
            'max_pages' => config('seo.max_pages', 20),
            'max_depth' => config('seo.max_depth', 2),
            'page_timeout' => config('seo.page_timeout', 30),
            'max_parallel_pages' => (int) env('SCAN_CONCURRENCY', config('seo.max_parallel_pages', 8)),
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
        Log::debug('[SCAN TRACE] node_process_start', [
            'scan_id' => $scan->id,
            'target' => 'multiScanner.js',
        ]);
        Log::info('[SCAN] Starting Node Scanner', ['scan_id' => $scan->id]);
        Log::debug('[SCAN TRACE] node_command', [
            'scan_id' => $scan->id,
            'command' => $process->getCommandLine(),
        ]);
        Log::debug('[SCAN] Node command', ['command' => $process->getCommandLine()]);
        try {
            $process->run(function (string $type, string $buffer) use ($scan): void {
                $trimmed = trim($buffer);

                if ($type === Process::ERR) {
                    Log::error('[NODE STDERR]', [
                        'scan_id' => $scan->id,
                        'output' => $trimmed,
                    ]);

                    return;
                }

                foreach (preg_split('/\r?\n/', $trimmed) as $line) {
                    if ($line === '') {
                        continue;
                    }

                    $payload = json_decode($line, true);
                    if (is_array($payload)) {
                        $this->persistScanEvent($scan->id, $payload);
                    }
                }

                Log::debug('[NODE STDOUT]', [
                    'scan_id' => $scan->id,
                    'output' => $trimmed,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('[SCAN TRACE] node_process_exception', [
                'scan_id' => $scan->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
        Log::debug('[SCAN TRACE] node_process_finished', [
            'scan_id' => $scan->id,
            'exit_code' => $process->getExitCode(),
            'exit_code_text' => $process->getExitCodeText(),
            'successful' => $process->isSuccessful(),
        ]);
        Log::info('[SCAN] Node Scanner finished', ['scan_id' => $scan->id]);

        if (!$process->isSuccessful()) {
            Log::error('RunScan Node Prozess fehlgeschlagen', [
                'scan_id' => $this->scanId,
                'error' => $process->getErrorOutput(),
            ]);

            $scan->update(['status' => 'failed']);
            return;
        }

        $report = Report::create([
            'id' => $scan->id,
            'url' => $scan->url,
            'type' => 'crawler',
            'status' => 'processing',
        ]);

        $reportPersistenceService->syncFromStorage($report);

        $report->load('results');
        app(IssueDetectionService::class)->detectAndStoreForReport($report);

        $scan->update(['status' => 'done']);
    }
}
