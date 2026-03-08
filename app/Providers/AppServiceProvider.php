<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useTailwind();

        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $settingKeys = [
            'max_pages',
            'max_depth',
            'page_timeout',
            'max_parallel_pages',
            'max_retries',
            'retry_delay',
            'max_scan_time',
        ];

        $settings = DB::table('system_settings')
            ->whereIn('key', $settingKeys)
            ->pluck('value', 'key');

        foreach ($settingKeys as $key) {
            $value = $settings[$key] ?? null;

            if ($value !== null && is_numeric($value)) {
                config(["seo.{$key}" => (int) $value]);
            }
        }
    }
}
