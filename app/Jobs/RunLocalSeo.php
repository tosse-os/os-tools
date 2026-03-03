<?php

namespace App\Jobs;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;

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
            base_path('node-scanner/localSeoScanner.js'),
            json_encode($options),
            $this->reportId
        ]);

        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            $report->update([
                'status' => 'aborted',
                'finished_at' => now(),
            ]);
            return;
        }

        $jsonPath = storage_path("scans/{$this->reportId}/0.json");

        if (!file_exists($jsonPath)) {
            $report->update([
                'status' => 'aborted',
                'finished_at' => now(),
            ]);
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        if (!$data) {
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
    }
}
