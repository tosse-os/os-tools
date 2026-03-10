<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Scan;
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

    public function progress(Scan $scan)
    {
        $path = storage_path("scans/{$scan->id}/progress.json");

        if (!File::exists($path)) {
            return response()->json([
                'status' => 'initializing',
                'current' => 0,
                'total' => Config::get('tools.limits.max_urls_per_scan', 10)
            ]);
        }

        $content = File::get($path);
        $json = json_decode($content, true);

        return response()->json([
            'status' => $json['status'] ?? 'running',
            'current' => $json['current'] ?? 0,
            'total' => $json['total'] ?? Config::get('tools.limits.max_urls_per_scan', 10)
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
        $scans = Scan::latest()->get(); // später: ->where('user_id', auth()->id())
        return view('scans.index', compact('scans'));
    }
    public function show(Scan $scan)
    {
        return view('scans.show', compact('scan'));
    }
    public function abort(Request $request)
    {
        $scanId = $request->input('scanId');

        if (!$scanId) {
            return response()->json(['error' => 'missing scanId'], 400);
        }

        $path = base_path("node-scanner/storage/app/abort-{$scanId}.flag");

        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, 'abort');

        return response()->json(['status' => 'abort_requested']);
    }
}
