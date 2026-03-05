<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CrawlerController;
use App\Http\Controllers\LocalSeoController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\QueueController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| CRAWLER REPORT
|--------------------------------------------------------------------------
*/

Route::get('/crawler', [CrawlerController::class, 'form'])->name('crawler.form');
Route::post('/crawler', [CrawlerController::class, 'start'])->name('crawler.start');

/*
|--------------------------------------------------------------------------
| LOCAL SEO REPORT (Platzhalter für nächstes Modul)
|--------------------------------------------------------------------------
*/

Route::get('/local-seo', [LocalSeoController::class, 'form'])->name('localseo.form');
Route::post('/local-seo', [LocalSeoController::class, 'start'])->name('localseo.start');

/*
|--------------------------------------------------------------------------
| REPORT OVERVIEW
|--------------------------------------------------------------------------
*/

Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/archive', [ReportController::class, 'archive'])->name('reports.archive');
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
Route::view('/logs/live', 'logs.live')->name('logs.live');
Route::get('/queues', [QueueController::class, 'index'])->name('queues.index');
