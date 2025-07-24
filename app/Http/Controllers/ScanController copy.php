<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Scan;
use App\Jobs\RunScan;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ScanController extends Controller
{
    public function form()
    {
        return view('multiscan.live');// /multiscan/live.blade.php
    }

    public function start(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $scan = Scan::create([
            'id' => (string) Str::uuid(),
            'url' => $request->url,
            'status' => 'queued'
        ]);

        RunScan::dispatch($scan->id);

        return response()->json(['scanId' => $scan->id]);
    }

    public function progress(Scan $scan)
    {
        $resultsCount = $scan->results()->count();

        return response()->json([
            'status' => $scan->status,
            'current' => $resultsCount,
            'total' => $scan->results_expected ?? 5,
            'url' => optional($scan->results()->latest()->first())->url ?? null
        ]);
    }

    public function result(Scan $scan, $index)
    {
        Log::debug('Scan result lookup:', [
            'scanId' => $scan->id,
            'index' => $index,
            'count' => $scan->results()->count(),
        ]);

        $result = $scan->results()->skip($index)->first();

        if (!$result) {
            Log::debug('No result found at index', [$index]);
            return response()->json([]);
        }

        Log::debug('RAW PAYLOAD:', [$result->payload]); // ✅


        return response()->json($result->payload);
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
}
