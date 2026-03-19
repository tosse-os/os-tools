<?php

namespace App\Http\Controllers;

use App\Models\Crawl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrawlController extends Controller
{
    public function show(Crawl $crawl)
    {
        $pages = $crawl->pages()
            ->orderByDesc('id')
            ->paginate(100);

        $summary = [
            'pages_crawled' => (int) ($crawl->pages_scanned + $crawl->pages_failed),
            'internal_links' => (int) $crawl->links()->where('type', 'internal')->count(),
            'external_links' => (int) $crawl->links()->where('type', 'external')->count(),
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

        $issuesByType = collect();
        if (Schema::hasTable('crawl_issues')) {
            $issuesByType = DB::table('crawl_issues')
                ->where('crawl_id', $crawl->id)
                ->selectRaw('type, COUNT(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type');
        }

        $issueReports = [
            'missing_alt' => (int) ($issuesByType['missing_alt'] ?? $crawl->pages()->where('alt_missing_count', '>', 0)->count()),
            'missing_h1' => (int) ($issuesByType['missing_h1'] ?? $crawl->pages()->where('h1_count', '=', 0)->count()),
            'broken_links' => (int) ($issuesByType['broken_link'] ?? $summary['broken_links']),
            'redirect_chains' => (int) ($issuesByType['redirect_chain'] ?? $crawl->links()->where('redirect_chain_length', '>', 1)->count()),
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
}
