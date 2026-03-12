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
            'city' => $this->city
        ]);

        $report = \App\Models\Report::findOrFail($this->reportId);

        $report->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $options = [
            'url' => $report->url,
            'keyword' => $this->keyword,
            'city' => $this->city,
        ];

        $process = new \Symfony\Component\Process\Process([
            'node',
            base_path('node-scanner/core/localSEOScanner.js'),
            json_encode($options),
            $this->reportId
        ]);

        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {

            Log::error('Local SEO Node Prozess fehlgeschlagen', [
                'report_id' => $this->reportId,
                'error' => $process->getErrorOutput()
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
                'path' => $jsonPath
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
                'report_id' => $this->reportId
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
}
