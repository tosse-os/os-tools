<?php

namespace App\Http\Controllers;

use App\Jobs\RunScan;
use App\Models\Analysis;
use App\Models\Project;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    $analysis = $this->findOrCreateAnalysis(
      auth()->id(),
      $request->url,
      null,
      null,
    );

    $report = Report::create([
      'id' => (string) Str::uuid(),
      'user_id' => auth()->id(),
      'analysis_id' => $analysis->id,
      'type' => 'crawler',
      'project_id' => $analysis->project_id,
      'url' => $request->url,
      'status' => 'queued'
    ]);

    RunScan::dispatch($report->id, []);

    return response()->json([
      'reportId' => $report->id
    ]);
  }

  private function findOrCreateAnalysis(?int $userId, string $url, ?string $keyword, ?string $city): Analysis
  {
    $domain = parse_url($url, PHP_URL_HOST) ?: $url;

    $project = Project::firstOrCreate(
      [
        'user_id' => $userId,
        'domain' => $domain,
      ],
      [
        'id' => (string) Str::uuid(),
        'name' => $domain,
      ],
    );

    return Analysis::firstOrCreate(
      [
        'project_id' => $project->id,
        'url' => $url,
        'keyword' => $keyword,
        'city' => $city,
      ],
      [
        'id' => (string) Str::uuid(),
      ],
    );
  }
}
