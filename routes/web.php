<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\MultiScanController;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;


Route::get('/', [ScanController::class, 'form'])->name('scan.form');

Route::get('/scan', [ScanController::class, 'form'])->name('scan.form');//form
Route::post('/scan', [ScanController::class, 'start'])->name('scan.start');//bg scan

Route::get('/scan/{scan}/live', [ScanController::class, 'live'])->name('scan.live');//polling

Route::get('/scan/{scan}/progress', [ScanController::class, 'progress'])->name('scan.progress');//json response
Route::get('/scan/{scan}/result/{index}', [ScanController::class, 'result'])->name('scan.result');

// Views
Route::get('/scans', [ScanController::class, 'index'])->name('scans.index');
Route::get('/scans/{scan}', [ScanController::class, 'show'])->name('scans.show');

//Exports
Route::get('/scans/{scan}/export', [\App\Http\Controllers\ScanController::class, 'exportCsv'])->name('scans.export.csv');

//Progress stuff

Route::post('/multiscan/abort', function (Request $request) {
  $scanId = $request->input('scanId');
  if (!$scanId) return response()->json(['error' => 'No scanId'], 400);

  File::put(storage_path("app/abort-$scanId.flag"), '');
  return response()->json(['aborted' => true]);
});
