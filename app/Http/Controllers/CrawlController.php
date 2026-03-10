<?php

namespace App\Http\Controllers;

use App\Models\Crawl;

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
}
