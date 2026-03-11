<?php

use App\Services\Crawler\CrawlerEventConsumer;
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

Artisan::command('crawl:consume-events {crawlId}', function (string $crawlId, CrawlerEventConsumer $consumer) {
    while (true) {
        $consumer->consume($crawlId, 2);
    }
})->purpose('Consume crawl events from Redis and persist crawl data.');
