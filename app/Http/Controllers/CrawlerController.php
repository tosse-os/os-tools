<?php

namespace App\Http\Controllers;

use App\Jobs\RunCrawl;
use App\Models\Analysis;
use App\Models\Crawl;
use App\Models\Project;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CrawlerController extends Controller
{
  public function index()
  {
    return view('crawler.form');
  }

  public function run(Request $request)
  {
    $request->validate([
      'url' => 'required|url'
    ]);

    $localseo = $this->findOrCreateLocalSeo(
      auth()->id(),
      $request->url,
      null,
      null,
    );

    $result = Report::create([
      'id' => (string) Str::uuid(),
      'user_id' => auth()->id(),
      'analysis_id' => $localseo->id,
      'type' => 'crawler',
      'url' => $request->url,
      'status' => 'queued'
    ]);

    Crawl::create([
      'id' => $result->id,
      'domain' => parse_url($request->url, PHP_URL_HOST) ?: $request->url,
      'root_url' => $request->url,
      'start_url' => $request->url,
      'status' => 'queued',
      'pages_discovered' => 1,
      'pages_scanned' => 0,
      'pages_failed' => 0,
    ]);

    RunCrawl::dispatch($result->id);

    return response()->json([
      'scanId' => $result->id
    ]);
  }

  private function findOrCreateLocalSeo(?int $userId, string $url, ?string $keyword, ?string $city): Analysis
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
