<?php

namespace App\Services\Crawler;

use App\Models\Crawl;

class CrawlProgressService
{
    public function incrementDiscovered(string $crawlId): void
    {
        Crawl::whereKey($crawlId)->increment('pages_discovered');
    }

    public function incrementScanned(string $crawlId): void
    {
        Crawl::whereKey($crawlId)->increment('pages_scanned');
    }

    public function incrementFailed(string $crawlId): void
    {
        Crawl::whereKey($crawlId)->increment('pages_failed');
    }
}
