<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Scan;
use App\Models\Crawl;
use App\Jobs\RunScan;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;


class ScanController extends Controller
{
    public function form()
    {
        return view('multiscan.live');// /multiscan/live.blade.php
    }

    public function start(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'checks' => 'nullable|array'
        ]);

        $scan = Scan::create([
            'id' => (string) Str::uuid(),
            'url' => $request->url,
            'status' => 'queued'
        ]);

        Crawl::create([
            'id' => $scan->id,
            'domain' => parse_url($request->url, PHP_URL_HOST) ?: $request->url,
            'start_url' => $request->url,
            'status' => 'queued',
            'pages_scanned' => 0,
            'pages_total' => 0,
            'created_at' => now(),
        ]);

        Log::debug('[SCAN TRACE] controller_start', [
            'scan_id' => $scan->id,
            'url' => $request->url,
            'checks' => $request->input('checks', []),
        ]);

        Log::debug('[SCAN TRACE] dispatching_scan_job', [
            'scan_id' => $scan->id,
        ]);

        RunScan::dispatch($scan->id, $request->input('checks', []));

        Log::debug('[SCAN TRACE] job_dispatched', [
            'scan_id' => $scan->id,
        ]);

        return response()->json(['scanId' => $scan->id]);
    }

    public function progress(Request $request, Scan $scan)
    {
        $scanDirectory = storage_path("scans/{$scan->id}");
        $path = $scanDirectory.'/progress.json';
        $eventsPath = $scanDirectory.'/events.jsonl';
        $eventCursor = (int) $request->query('event_cursor', 0);
        $events = [];

        if (File::exists($eventsPath)) {
            $lines = preg_split('/\r?\n/', trim(File::get($eventsPath)));
            $lines = array_filter($lines, static fn ($line) => $line !== '');
            $parsedEvents = array_map(static fn ($line) => json_decode($line, true), $lines);
            $events = array_values(array_filter($parsedEvents, static fn ($event) => is_array($event)));
        }

        $resultFilePaths = File::exists($scanDirectory) ? (File::glob($scanDirectory.'/*.json') ?: []) : [];

        $resultFiles = collect($resultFilePaths)
            ->filter(static function (string $filePath): bool {
                return preg_match('/\/\d+\.json$/', $filePath) === 1;
            })
            ->sort(static function (string $left, string $right): int {
                return (int) basename($left, '.json') <=> (int) basename($right, '.json');
            })
            ->values();

        foreach ($resultFiles as $resultFile) {
            $result = json_decode(File::get($resultFile), true);

            if (!is_array($result)) {
                continue;
            }

            $events[] = [
                'type' => 'page_scanned',
                'url' => $result['url'] ?? null,
                'status' => $result['statusCheck']['status'] ?? null,
                'alt_count' => $result['altCheck']['altMissing'] ?? 0,
                'heading_count' => isset($result['headingCheck']['list']) && is_array($result['headingCheck']['list'])
                    ? count($result['headingCheck']['list'])
                    : 0,
                'error' => $result['error'] ?? null,
            ];
        }

        $newEvents = array_slice($events, max($eventCursor, 0));

        if (!File::exists($path)) {
            return response()->json([
                'type' => 'crawl_progress',
                'status' => 'initializing',
                'stage' => 'queued',
                'current' => 0,
                'total' => Config::get('tools.limits.max_urls_per_scan', 10),
                'scanned_pages' => 0,
                'queue_size' => 0,
                'current_url' => null,
                'events' => $newEvents,
                'event_cursor' => count($events),
            ]);
        }

        $content = File::get($path);
        $json = json_decode($content, true);

        return response()->json([
            'type' => $json['type'] ?? 'crawl_progress',
            'status' => $json['status'] ?? 'running',
            'stage' => $json['stage'] ?? 'scanning',
            'current' => $json['current'] ?? 0,
            'total' => $json['total'] ?? Config::get('tools.limits.max_urls_per_scan', 10),
            'scanned_pages' => $json['scanned_pages'] ?? ($json['current'] ?? 0),
            'queue_size' => $json['queue_size'] ?? max(($json['total'] ?? 0) - ($json['current'] ?? 0), 0),
            'current_url' => $json['current_url'] ?? null,
            'events' => $newEvents,
            'event_cursor' => count($events),
        ]);
    }

    public function result(Scan $scan, $index)
    {
        $file = storage_path("scans/{$scan->id}/{$index}.json");

        if (!File::exists($file)) {
            return response()->json([]);
        }

        $json = File::get($file);
        return response()->json(json_decode($json, true));
    }

    public function index()
    {
        $scans = Scan::orderByDesc('created_at')->get();
        return view('scans.index', compact('scans'));
    }

    public function show(Scan $scan)
    {
        $scanDirectory = storage_path("scans/{$scan->id}");

        $resultFiles = [];
        if (File::exists($scanDirectory)) {
            $resultFiles = collect(File::glob($scanDirectory.'/*.json') ?: [])
                ->filter(function ($file) {
                    return preg_match('/\/\d+\.json$/', $file);
                })
                ->sortBy(function ($file) {
                    return (int) basename($file, '.json');
                })
                ->values();
        }

        $results = [];
        foreach ($resultFiles as $file) {
            $json = json_decode(File::get($file), true);
            if ($json) {
                $results[] = $json;
            }
        }

        return view('scans.show', compact('scan', 'results'));
    }

    public function abort(Request $request)
    {
        $request->validate([
            'scanId' => 'required|uuid'
        ]);

        $scan = Scan::findOrFail($request->scanId);

        $scan->update([
            'status' => 'aborted',
            'finished_at' => now(),
        ]);

        Crawl::whereKey($scan->id)->update([
            'status' => 'aborted',
            'finished_at' => now(),
        ]);

        $scanDir = storage_path("scans/{$scan->id}");
        File::put($scanDir.'/progress.json', json_encode([
            'type' => 'crawl_progress',
            'status' => 'aborted',
            'stage' => 'aborted',
            'current' => (int) $scan->current,
            'total' => (int) $scan->total,
            'scanned_pages' => (int) $scan->current,
            'queue_size' => max((int) $scan->total - (int) $scan->current, 0),
            'current_url' => null,
        ]));

        return response()->json(['ok' => true]);
    }
}
