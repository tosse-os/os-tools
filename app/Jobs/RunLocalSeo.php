<?php

namespace App\Jobs;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;
use App\Services\IssueDetectionService;

class RunLocalSeo implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    private const NODE_LINE_MAX_LENGTH = 500;

    protected string $reportId;
    protected string $keyword;
    protected string $city;

    public function __construct(string $reportId, string $keyword, string $city)
    {
        $this->reportId = $reportId;
        $this->keyword = $keyword;
        $this->city = $city;
    }

    public function handle(): void
    {
        Log::info('Local SEO Job gestartet', [
            'report_id' => $this->reportId,
            'keyword' => $this->keyword,
            'city' => $this->city,
        ]);

        $report = Report::findOrFail($this->reportId);

        $report->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $options = [
            'url' => $report->url,
            'keyword' => $this->keyword,
            'city' => $this->city,
        ];

        $command = [
            'node',
            base_path('node-scanner/core/localSEOScanner.js'),
            json_encode($options),
            $this->reportId,
        ];

        $process = new Process($command);
        $process->setTimeout(null);

        $scannerLogger = Log::channel('scanner');
        $debugScannerOutput = (bool) config('app.local_seo_scanner_debug', false);

        $process->run(function (string $type, string $buffer) use ($scannerLogger, $debugScannerOutput, $process): void {
            $this->logNodeOutput($buffer, $type, $scannerLogger, $debugScannerOutput);

            // Keep buffers small while process is still running.
            $process->clearOutput();
            $process->clearErrorOutput();
        });

        $process->clearOutput();
        $process->clearErrorOutput();

        if (!$process->isSuccessful()) {
            Log::error('Local SEO Node Prozess fehlgeschlagen', [
                'report_id' => $this->reportId,
                'exit_code' => $process->getExitCode(),
                'status' => $process->getStatus(),
            ]);

            $report->update([
                'status' => 'aborted',
                'finished_at' => now(),
            ]);

            return;
        }

        $jsonPath = storage_path("scans/{$this->reportId}/0.json");

        if (!file_exists($jsonPath)) {
            Log::error('Local SEO JSON Ergebnis fehlt', [
                'report_id' => $this->reportId,
                'path' => $jsonPath,
            ]);

            $report->update([
                'status' => 'aborted',
                'finished_at' => now(),
            ]);

            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        if (!$data) {
            Log::error('Local SEO JSON konnte nicht gelesen werden', [
                'report_id' => $this->reportId,
            ]);

            $report->update([
                'status' => 'aborted',
                'finished_at' => now(),
            ]);

            return;
        }

        $report->update([
            'score' => $data['score'] ?? 0,
            'status' => 'done',
            'finished_at' => now(),
        ]);

        $report->results()->create([
            'report_id' => $report->id,
            'module' => 'local_seo',
            'url' => $report->url,
            'position' => 0,
            'score' => $data['score'] ?? 0,
            'payload' => $data,
        ]);

        $report->load('results');
        app(IssueDetectionService::class)->detectAndStoreForReport($report);
    }

    private function logNodeOutput(string $buffer, string $type, $scannerLogger, bool $debugScannerOutput): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $buffer, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '') {
                continue;
            }

            $truncatedLine = mb_strimwidth($trimmedLine, 0, self::NODE_LINE_MAX_LENGTH, '...');

            if ($this->isProblemLogLine($trimmedLine)) {
                $scannerLogger->warning('Node scanner event', [
                    'stream' => $type,
                    'message' => $truncatedLine,
                ]);

                continue;
            }

            if ($debugScannerOutput) {
                $scannerLogger->debug('Node scanner debug', [
                    'stream' => $type,
                    'message' => $truncatedLine,
                ]);
            }
        }
    }

    private function isProblemLogLine(string $line): bool
    {
        $line = strtolower($line);

        return str_contains($line, 'error')
            || str_contains($line, 'warn')
            || str_contains($line, 'fail');
    }
}
