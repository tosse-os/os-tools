<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('scan:workers', function () {
    $workers = max(1, (int) env('SCAN_WORKERS', 4));
    $snapshotPath = storage_path('app/scan-workers.json');

    $this->info("Starting {$workers} scan worker(s)...");

    $processes = [];

    for ($i = 0; $i < $workers; $i++) {
        $process = new Process([
            PHP_BINARY,
            'artisan',
            'queue:work',
            '--queue=default',
            '--sleep=1',
            '--tries=3',
            '--timeout=120',
        ], base_path());

        $process->setTimeout(null);
        $process->start();

        $processes[] = $process;
        $this->line("Worker #" . ($i + 1) . " started (pid: " . ($process->getPid() ?? 'n/a') . ")");
    }

    $writeSnapshot = function (string $status = 'running') use (&$processes, $snapshotPath): void {
        $activeWorkers = 0;

        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $activeWorkers++;
            }
        }

        file_put_contents($snapshotPath, json_encode([
            'active_workers' => $activeWorkers,
            'status' => $status,
            'heartbeat' => now()->toISOString(),
        ], JSON_PRETTY_PRINT));
    };

    $stopWorkers = function () use (&$processes, $writeSnapshot): void {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->signal(SIGTERM);
            }
        }

        $writeSnapshot('stopped');
    };

    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () use ($stopWorkers): void {
        $stopWorkers();
        exit(0);
    });
    pcntl_signal(SIGTERM, function () use ($stopWorkers): void {
        $stopWorkers();
        exit(0);
    });

    while (true) {
        $writeSnapshot();

        foreach ($processes as $index => $process) {
            if (!$process->isRunning()) {
                $this->warn("Worker #" . ($index + 1) . " exited, restarting...");

                $replacement = new Process([
                    PHP_BINARY,
                    'artisan',
                    'queue:work',
                    '--queue=default',
                    '--sleep=1',
                    '--tries=3',
                    '--timeout=120',
                ], base_path());

                $replacement->setTimeout(null);
                $replacement->start();
                $processes[$index] = $replacement;
            }
        }

        sleep(5);
    }
})->purpose('Launch and supervise multiple scan queue workers');
