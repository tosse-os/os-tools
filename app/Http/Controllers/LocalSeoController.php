<?php

namespace App\Http\Controllers;

use App\Jobs\RunLocalSeo;
use App\Models\Analysis;
use App\Models\Project;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    $localseo = $this->findOrCreateLocalSeo(
      auth()->id(),
      $request->url,
      $request->keyword,
      $request->city,
    );

    $result = Report::create([
      'id' => (string) Str::uuid(),
      'user_id' => auth()->id(),
      'analysis_id' => $localseo->id,
      'type' => 'local_seo',
      'url' => $request->url,
      'keyword' => $request->keyword,
      'city' => $request->city,
      'status' => 'queued'
    ]);

    RunLocalSeo::dispatch(
      $result->id,
      $request->keyword,
      $request->city
    );

    return response()->json([
      'resultId' => $result->id
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
