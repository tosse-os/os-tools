<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Analysis;
use App\Models\Project;
use App\Models\Report;
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
        return view('multiscan.live');
    }

    public function start(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'checks' => 'nullable|array'
        ]);

        $analysis = $this->findOrCreateAnalysis(auth()->id(), $request->url);

        $report = Report::create([
            'id' => (string) Str::uuid(),
            'user_id' => auth()->id(),
            'analysis_id' => $analysis->id,
            'project_id' => $analysis->project_id,
            'type' => 'crawler',
            'url' => $request->url,
            'status' => 'queued',
        ]);

        Scan::updateOrCreate(
            ['id' => $report->id],
            ['url' => $request->url, 'status' => 'queued']
        );

        Log::debug('[SCAN TRACE] dispatching_scan_job', ['scan_id' => $report->id]);

        RunScan::dispatch($report->id, $request->input('checks', []), true);

        return response()->json(['scanId' => $report->id, 'reportId' => $report->id]);
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

        $json = json_decode(File::get($path), true);

        return response()->json([
            'status' => $json['status'] ?? 'running',
            'current' => $json['current'] ?? 0,
            'total' => $json['total'] ?? Config::get('tools.limits.max_urls_per_scan', 10)
        ]);
    }

    public function result(Scan $scan, $index)
    {
        $file = storage_path("scans/{$scan->id}/pages.json");

        if (!File::exists($file)) {
            return response()->json([]);
        }

        $pages = json_decode(File::get($file), true);
        return response()->json($pages[(int) $index] ?? []);
    }

    public function index()
    {
        $scans = Scan::latest()->get();
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

    private function findOrCreateAnalysis(?int $userId, string $url): Analysis
    {
        $domain = parse_url($url, PHP_URL_HOST) ?: $url;

        $project = Project::firstOrCreate(
            ['user_id' => $userId, 'domain' => $domain],
            ['id' => (string) Str::uuid(), 'name' => $domain],
        );

        return Analysis::firstOrCreate(
            ['project_id' => $project->id, 'url' => $url, 'keyword' => null, 'city' => null],
            ['id' => (string) Str::uuid()],
        );
    }
}
