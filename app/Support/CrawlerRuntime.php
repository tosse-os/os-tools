<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class CrawlerRuntime
{
    public static function redisEnabledSetting(): bool
    {
        if (!Schema::hasTable('system_settings')) {
            return false;
        }

        $value = DB::table('system_settings')
            ->where('key', 'crawler_use_redis')
            ->value('value');

        return (int) ($value ?? 0) === 1;
    }

    public static function redisReachable(): bool
    {
        try {
            return (string) Redis::connection()->ping() === 'PONG';
        } catch (\Throwable) {
            return false;
        }
    }

    public static function useRedis(): bool
    {
        return self::redisEnabledSetting() && self::redisReachable();
    }

    public static function assertRedisOrWarn(): void
    {
        if (!self::redisEnabledSetting()) {
            return;
        }

        if (!self::redisReachable()) {
            $message = 'Redis queue enabled but Redis server not reachable.';
            Log::warning($message);
            throw new \RuntimeException($message);
        }
    }
}
