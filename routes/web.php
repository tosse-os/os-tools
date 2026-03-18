<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CrawlerController;
use App\Http\Controllers\LocalSeoController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CrawlController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\CrawlerRuntimeController;
use App\Services\Crawler\CrawlLinkPersister;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| LOCAL SEO REPORT
|--------------------------------------------------------------------------
*/

Route::get('/local-seo', [LocalSeoController::class, 'form'])->name('localseo.form');
Route::post('/local-seo', [LocalSeoController::class, 'start'])->name('localseo.start');

/*
|--------------------------------------------------------------------------
| CRAWL ENTRY POINT
|--------------------------------------------------------------------------
*/

Route::get('/crawl', [CrawlerController::class, 'index'])->name('crawl.index');
Route::post('/crawl', [CrawlerController::class, 'run'])->name('crawl.run');
Route::get('/crawls', [CrawlController::class, 'index'])->name('crawls.index');
Route::get('/crawls/{crawl}', [CrawlController::class, 'show'])->name('crawls.show');
Route::post('/crawls/{crawl}/rerun', [CrawlController::class, 'rerun'])->name('crawls.rerun');


/*
|--------------------------------------------------------------------------
| PROJECTS & ANALYSES
|--------------------------------------------------------------------------
*/

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

Route::get('/analyses/{analysis}', [AnalysisController::class, 'show'])->name('analyses.show');

/*
|--------------------------------------------------------------------------
| REPORT OVERVIEW
|--------------------------------------------------------------------------
*/

Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/archive', [ReportController::class, 'archive'])->name('reports.archive');
Route::get('/reports/compare', [ReportController::class, 'compare'])->name('reports.compare');
Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');

Route::get('/reports/{report}/status', function (\App\Models\Report $report) {
  return response()->json([
    'status' => $report->status,
    'score' => $report->score,
    'started_at' => $report->started_at,
    'finished_at' => $report->finished_at,
  ]);
});

/*
|--------------------------------------------------------------------------
| LOGS & DEBUG
|--------------------------------------------------------------------------
*/

Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
Route::get('/logs/raw', [LogController::class, 'raw'])->name('logs.raw');
Route::post('/logs/clear', [LogController::class, 'clear'])->name('logs.clear');
Route::view('/logs/live', 'logs.live')->name('logs.live');

Route::get('/queues', [QueueController::class, 'index'])->name('queues.index');
Route::get('/system/workers', [QueueController::class, 'workers'])->name('system.workers');

/*
|--------------------------------------------------------------------------
| ADMIN SETTINGS
|--------------------------------------------------------------------------
*/

Route::get('/admin/settings', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
Route::post('/admin/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.update');

Route::post('/internal/crawler/event', [CrawlerRuntimeController::class, 'event'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('crawler.runtime.event');
Route::post('/internal/crawler/next-task', [CrawlerRuntimeController::class, 'nextTask'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('crawler.runtime.next-task');

Route::get('/debug/link-test', function (CrawlLinkPersister $persister) {
    $crawlId = request('crawl_id');

    if (!$crawlId) {
        return response()->json([
            'ok' => false,
            'error' => 'Pass crawl_id query parameter pointing to an existing crawl id.',
        ], 422);
    }

    $payload = [
        'source_url' => 'https://example.com',
        'target_url' => 'https://example.com/page',
        'type' => 'internal',
        'status_code' => 200,
    ];

    $persister->persist($crawlId, $payload);

    $stored = DB::table('crawl_links')
        ->where('crawl_id', $crawlId)
        ->where('source_url', $payload['source_url'])
        ->where('target_url', $payload['target_url'])
        ->first();

    return response()->json([
        'ok' => (bool) $stored,
        'crawl_id' => $crawlId,
        'stored' => $stored,
    ]);
})->name('debug.link-test');
