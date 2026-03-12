<?php

namespace App\Services;

use App\Support\CrawlerRuntime;
use Illuminate\Support\Facades\Log;

class SystemHealthService
{
    /** @return array<int, string> */
    public function collectWarnings(): array
    {
        $warnings = [];

        if (CrawlerRuntime::redisEnabledSetting() && !CrawlerRuntime::redisReachable()) {
            $warnings[] = 'Redis queue enabled but Redis server not reachable.';
        }

        $queueConnection = (string) config('queue.default');
        if (CrawlerRuntime::redisEnabledSetting() && $queueConnection !== 'redis') {
            $warnings[] = 'Redis queue is enabled but queue driver is not set to redis.';
        }

        $logPath = storage_path('logs');
        if (!is_dir($logPath) || !is_writable($logPath)) {
            $warnings[] = 'Storage logs directory is not writable.';
        }

        if (!$this->nodeAvailable()) {
            $warnings[] = 'Node crawler runtime is not available (optional check).';
        }

        foreach ($warnings as $warning) {
            Log::warning($warning);
        }

        return $warnings;
    }

    private function nodeAvailable(): bool
    {
        $nodeBinary = trim((string) shell_exec('command -v node 2>/dev/null'));

        return $nodeBinary !== '';
    }
}
