<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CrawlerController;
use App\Http\Controllers\LocalSeoController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\AnalysisController;

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
| LOCAL SEO REPORT
|--------------------------------------------------------------------------
*/

Route::get('/local-seo', [LocalSeoController::class, 'form'])->name('localseo.form');
Route::post('/local-seo', [LocalSeoController::class, 'start'])->name('localseo.start');

/*
|--------------------------------------------------------------------------
| MULTI SCAN / LIVE SCANNER
|--------------------------------------------------------------------------
*/

Route::get('/scan', [ScanController::class, 'form'])->name('scan.form');
Route::post('/scan', [ScanController::class, 'start'])->name('scan.start');

Route::get('/scan/{scan}/progress', [ScanController::class, 'progress']);
Route::get('/scans/{scan}/progress', [ScanController::class, 'progress'])->name('scans.progress');
Route::get('/scan/{scan}/result/{index}', [ScanController::class, 'result']);

Route::post('/multiscan/abort', [ScanController::class, 'abort']);

Route::get('/scans', [ScanController::class, 'index'])->name('scans.index');
Route::get('/scans/{scan}', [ScanController::class, 'show'])->name('scans.show');


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
Route::view('/logs/live', 'logs.live')->name('logs.live');

Route::get('/queues', [QueueController::class, 'index'])->name('queues.index');
Route::get('/system/workers', [QueueController::class, 'workers']);

/*
|--------------------------------------------------------------------------
| ADMIN SETTINGS
|--------------------------------------------------------------------------
*/

Route::get('/admin/settings', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
Route::post('/admin/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.update');
