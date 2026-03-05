<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class RunMultiScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $scanId;
    public array $options;

    public function __construct(string $scanId, array $options)
    {
        $this->scanId = $scanId;
        $this->options = $options;
    }

    public function handle(): void
    {
        Log::info('RunMultiScan gestartet', [
            'scanId' => $this->scanId,
            'options' => $this->options
        ]);

        $process = new Process([
            'node',
            base_path('node-scanner/multiScanner.js'),
            json_encode($this->options),
            $this->scanId
        ]);

        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {

            Log::error('MultiScanner Node Fehler', [
                'scan_id' => $this->scanId,
                'error' => $process->getErrorOutput()
            ]);
        }
    }
}
