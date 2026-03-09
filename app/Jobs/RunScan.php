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

        $process = new Process([
            'node',
            base_path('node-scanner/core/scanner.js'),
            json_encode($options),
        ]);

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
                Log::debug('[NODE OUTPUT]', [
                    'scan_id' => $report->id,
                    'type' => $type,
                    'output' => $buffer,
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
        Storage::put("{$directory}/progress.json", json_encode([
            'current' => 1,
            'total' => 1,
            'status' => 'done',
        ]));

        $reportPersistenceService->syncFromStorage($report->fresh());
    }

    private function runMultiScan(Scan $scan): void
    {
        Log::info('[SCAN] Job gestartet', [
            'scan_id' => $scan->id,
            'url' => $scan->url,
        ]);

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

        $process = new Process([
            'node',
            base_path('node-scanner/core/multiScanner.js'),
            json_encode($options),
            $this->scanId,
        ]);

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
                Log::debug('[NODE OUTPUT]', [
                    'scan_id' => $scan->id,
                    'type' => $type,
                    'output' => $buffer,
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

        $scan->update(['status' => 'done']);
    }
}
