<?php

namespace App\Http\Controllers;

use App\Jobs\RunCrawlPipeline;
use App\Models\Analysis;
use App\Models\Crawl;
use App\Models\Project;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class CrawlController extends Controller
{
    public function index()
    {
        $crawls = Crawl::query()
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('crawls.index', [
            'crawls' => $crawls,
        ]);
    }

    public function show(Crawl $crawl)
    {
        $pages = $crawl->pages()
            ->orderByDesc('id')
            ->paginate(100);

        return view('crawls.show', [
            'crawl' => $crawl,
            'pages' => $pages,
        ]);
    }


    public function rerun(Crawl $crawl): RedirectResponse
    {
        $analysis = $this->findOrCreateAnalysis(
            auth()->id(),
            $crawl->start_url,
            null,
            null,
        );

        $report = Report::create([
            'id' => (string) Str::uuid(),
            'user_id' => auth()->id(),
            'analysis_id' => $analysis->id,
            'type' => 'crawler',
            'url' => $crawl->start_url,
            'status' => 'queued',
        ]);

        Crawl::create([
            'id' => $report->id,
            'domain' => parse_url($crawl->start_url, PHP_URL_HOST) ?: $crawl->start_url,
            'root_url' => $crawl->root_url ?: $crawl->start_url,
            'start_url' => $crawl->root_url ?: $crawl->start_url,
            'status' => 'queued',
            'pages_discovered' => 0,
            'pages_scanned' => 0,
            'pages_failed' => 0,
        ]);

        RunCrawlPipeline::dispatch($report->id);

        return redirect()
            ->route('crawls.show', $report->id)
            ->with('status', 'Crawl was queued again.');
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
