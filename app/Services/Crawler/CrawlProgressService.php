<?php

namespace App\Services\Crawler;

use App\Models\Crawl;
use Illuminate\Support\Facades\DB;

class CrawlProgressService
{
    public function incrementScanned(string $crawlId): void
    {
        Crawl::whereKey($crawlId)->update([
            'pages_scanned' => DB::raw('pages_scanned + 1'),
            'updated_at' => now(),
        ]);
    }

    public function incrementFailed(string $crawlId): void
    {
        Crawl::whereKey($crawlId)->update([
            'pages_failed' => DB::raw('pages_failed + 1'),
            'updated_at' => now(),
        ]);
    }

    public function incrementDiscovered(string $crawlId): void
    {
        Crawl::whereKey($crawlId)->update([
            'pages_discovered' => DB::raw('pages_discovered + 1'),
            'updated_at' => now(),
        ]);
    }
}
