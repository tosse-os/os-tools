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
        $scan = Scan::findOrFail($this->scanId);

        $scan->update(['status' => 'running']);

        $options = [
            'url' => $scan->url,
            'checks' => $this->checks,
            'maxPages' => config('tools.limits.max_urls_per_scan', 6),
        ];

        Log::debug('🟠 Übergabe an Node-Prozess:', [
            'scanId' => $this->scanId,
            'options' => $options
        ]);

        $process = new Process([
            'node',
            base_path('node-scanner/multiscanner.js'),
            json_encode($options),
            $this->scanId
        ]);



        $process->start(); // ⏱️ Sofortiger Start – ohne Warten auf Output

        // Done. Die Ausgabe-Dateien werden durch Node geschrieben.
        // Wenn du willst, kannst du hier später einen zweiten Job triggern,
        // der prüft, wann alles fertig ist – z. B. für DB-Eintrag oder Export.
    }
}
