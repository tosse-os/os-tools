<?php

namespace App\Http\Controllers;

use App\Jobs\RunScan;
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

        $summary = [
            'pages_crawled' => (int) $crawl->pages()->count(),
            'internal_links' => (int) $crawl->links()->where('link_type', 'internal')->count(),
            'external_links' => (int) $crawl->links()->where('link_type', 'external')->count(),
            'broken_links' => (int) $crawl->links()->where('status_code', '>=', 400)->count(),
            'redirects' => (int) $crawl->links()->whereIn('status_code', [301, 302, 307, 308])->count(),
            'duplicate_pages' => (int) $crawl->pages()
                ->selectRaw('COALESCE(text_hash, content_hash) as duplicate_hash')
                ->where(function ($query) {
                    $query->whereNotNull('text_hash')->orWhereNotNull('content_hash');
                })
                ->groupBy('duplicate_hash')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count(),
            'broken_pages' => (int) $crawl->pages()->where('status_code', '>=', 400)->count(),
        ];

        $issueReports = [
            'missing_alt' => $crawl->pages()->where('alt_missing_count', '>', 0)->count(),
            'missing_h1' => $crawl->pages()->where('h1_count', '=', 0)->count(),
            'broken_links' => $summary['broken_links'],
            'redirect_chains' => $crawl->links()->where('redirect_chain_length', '>', 1)->count(),
            'duplicate_pages' => $summary['duplicate_pages'],
            'orphan_pages' => $crawl->pages()->where('internal_links_in', 0)->where('depth', '>', 0)->count(),
        ];

        $brokenLinks = $crawl->links()
            ->where('status_code', '>=', 400)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $redirectChains = $crawl->links()
            ->whereIn('status_code', [301, 302, 307, 308])
            ->where('redirect_chain_length', '>', 0)
            ->orderByDesc('redirect_chain_length')
            ->limit(200)
            ->get();

        $orphanPages = $crawl->pages()
            ->where('internal_links_in', 0)
            ->where('depth', '>', 0)
            ->orderBy('url')
            ->get();

        return view('crawls.show', [
            'crawl' => $crawl,
            'pages' => $pages,
            'summary' => $summary,
            'issueReports' => $issueReports,
            'brokenLinks' => $brokenLinks,
            'redirectChains' => $redirectChains,
            'orphanPages' => $orphanPages,
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
            'start_url' => $crawl->start_url,
            'status' => 'queued',
            'pages_scanned' => 0,
            'pages_total' => 0,
            'created_at' => now(),
        ]);

        RunScan::dispatch($report->id, []);

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
