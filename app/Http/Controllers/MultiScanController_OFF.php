<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use App\Jobs\RunMultiScan;
use Illuminate\Support\Facades\Log;

class MultiScanController extends Controller
{
    public function live()
    {
        return view('multiscan.live');
    }

    public function run(Request $request)
    {

        $scanId = (string) Str::uuid();
        $url = $request->input('url');

        $options = [
            'url' => $url,
            'checks' => config('tools.enabled_checks'),
            'maxUrls' => config('tools.limits.max_urls_per_scan'),
        ];

        dispatch(new RunMultiScan($scanId, $options));

        return response()->json(['scanId' => $scanId]);
    }
}

