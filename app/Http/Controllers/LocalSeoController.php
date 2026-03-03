<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Report;
use Illuminate\Support\Str;
use App\Jobs\RunLocalSeo;

class LocalSeoController extends Controller
{
  public function form()
  {
    return view('localseo.form');
  }

  public function start(Request $request)
  {
    $request->validate([
      'url' => 'required|url',
      'keyword' => 'required|string',
      'city' => 'required|string'
    ]);

    $report = Report::create([
      'id' => (string) Str::uuid(),
      'user_id' => auth()->id(),
      'type' => 'local_seo',
      'url' => $request->url,
      'status' => 'queued'
    ]);

    RunLocalSeo::dispatch(
      $report->id,
      $request->keyword,
      $request->city
    );

    return response()->json([
      'reportId' => $report->id
    ]);
  }
}
