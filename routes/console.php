<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('scan:workers', function () {
    $workers = (int) env('SCAN_WORKERS', 4);
    $concurrency = (int) env('SCAN_CONCURRENCY', 5);

    if ($workers < 1) {
        $workers = 1;
    }

    if ($concurrency < 1) {
        $concurrency = 1;
    }

    $this->info("Starting {$workers} scan workers with concurrency {$concurrency}.");

    for ($index = 1; $index <= $workers; $index++) {
        $command = sprintf(
            'php artisan queue:work --queue=default --sleep=1 --tries=3 --name=scan-worker-%d',
            $index
        );

        if (DIRECTORY_SEPARATOR === '\\') {
            pclose(popen('start /B '.$command, 'r'));
        } else {
            $process = proc_open($command.' > /dev/null 2>&1 &', [], $pipes);
            if (is_resource($process)) {
                proc_close($process);
            }
        }
    }

    $this->info('Scan workers launched.');
})->purpose('Launch multiple scan queue workers');

Artisan::command('crawl:workers', function () {
    $workers = max((int) env('CRAWL_WORKERS', 4), 1);

    $this->info("Starting {$workers} crawler workers.");

    for ($index = 1; $index <= $workers; $index++) {
        $command = sprintf(
            'node %s > /dev/null 2>&1 &',
            escapeshellarg(base_path('node-scanner/core/crawler-worker.js')),
        );

        if (DIRECTORY_SEPARATOR === '\\') {
            pclose(popen('start /B '.$command, 'r'));
        } else {
            $process = proc_open($command, [], $pipes);
            if (is_resource($process)) {
                proc_close($process);
            }
        }
    }

    $consumerCommand = 'php artisan crawl:consume-events > /dev/null 2>&1 &';

    if (DIRECTORY_SEPARATOR === '\\') {
        pclose(popen('start /B '.$consumerCommand, 'r'));
    } else {
        $process = proc_open($consumerCommand, [], $pipes);
        if (is_resource($process)) {
            proc_close($process);
        }
    }

    $this->info('Crawler workers and event consumer launched.');
})->purpose('Launch distributed crawler workers and Laravel crawl event consumer');
