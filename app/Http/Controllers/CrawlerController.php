<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Report;
use Illuminate\Support\Str;
use App\Jobs\RunScan;

class CrawlerController extends Controller
{
  public function form()
  {
    return view('crawler.form');
  }

  public function start(Request $request)
  {
    $request->validate([
      'url' => 'required|url'
    ]);

    $report = Report::create([
      'id' => (string) Str::uuid(),
      'user_id' => auth()->id(),
      'type' => 'crawler',
      'url' => $request->url,
      'status' => 'queued'
    ]);

    RunScan::dispatch($report->id, []);

    return response()->json([
      'reportId' => $report->id
    ]);
  }
}
