<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    private const SETTING_KEYS = [
        'max_pages',
        'max_depth',
        'page_timeout',
        'max_parallel_pages',
        'max_retries',
        'retry_delay',
        'max_scan_time',
        'crawler_use_redis',
    ];

    public function index(): View
    {
        $storedSettings = collect();

        if (Schema::hasTable('system_settings')) {
            $storedSettings = DB::table('system_settings')
                ->whereIn('key', self::SETTING_KEYS)
                ->pluck('value', 'key');
        }

        $settings = [];

        foreach (self::SETTING_KEYS as $key) {
            if ($key === 'crawler_use_redis') {
                $settings[$key] = (bool) ((int) ($storedSettings[$key] ?? 0));
                continue;
            }

            $settings[$key] = (int) ($storedSettings[$key] ?? config("seo.{$key}"));
        }

        return view('admin.settings', [
            'settings' => $settings,
            'settingKeys' => self::SETTING_KEYS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];

        foreach (self::SETTING_KEYS as $key) {
            if ($key === 'crawler_use_redis') {
                $rules[$key] = ['nullable', 'boolean'];
                continue;
            }

            $rules[$key] = ['required', 'integer', 'min:1'];
        }

        $validated = $request->validate($rules);
        $validated['crawler_use_redis'] = $request->boolean('crawler_use_redis');

        if (! Schema::hasTable('system_settings')) {
            return redirect()
                ->route('admin.settings.index')
                ->with('status', 'Settings table is not available yet. Please run migrations.');
        }

        $now = now();
        $rows = [];

        foreach (self::SETTING_KEYS as $key) {
            $rows[] = [
                'key' => $key,
                'value' => (string) (int) $validated[$key],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('system_settings')->upsert($rows, ['key'], ['value', 'updated_at']);

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Settings saved successfully.');
    }
}
