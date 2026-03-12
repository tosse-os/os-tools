<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Scan;
use App\Models\Crawl;
use App\Jobs\RunCrawl;
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

        RunCrawl::dispatch($scan->id);

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
            $lines = preg_split('/\r?\n/', File::get($eventsPath));

            foreach ($lines as $line) {
                $line = trim((string) $line);

                if ($line === '') {
                    continue;
                }

                $event = json_decode($line, true);
                if (is_array($event)) {
                    $events[] = $event;
                }
            }
        }

        $safeCursor = max($eventCursor, 0);
        $newEvents = $safeCursor >= count($events)
            ? []
            : array_slice($events, $safeCursor);

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
