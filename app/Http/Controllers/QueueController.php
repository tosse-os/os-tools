<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
  public function index()
  {
    $jobs = DB::table('jobs')
      ->select('id', 'queue', 'attempts')
      ->orderByDesc('id')
      ->get();

    $failedJobs = DB::table('failed_jobs')
      ->select('id', 'payload', 'exception', 'failed_at')
      ->orderByDesc('id')
      ->get();

    $activeWorkers = (int) env('SCAN_WORKERS', 4);
    $activeScans = Scan::where('status', 'running')->count();
    $queuedScans = Scan::where('status', 'queued')->count();
    $failedScans = Scan::where('status', 'failed')->count();

    return view('queues.index', compact('jobs', 'failedJobs', 'activeWorkers', 'activeScans', 'queuedScans', 'failedScans'));
  }

  public function workers(): JsonResponse
  {
    $activeWorkers = (int) env('SCAN_WORKERS', 4);
    $lastScan = Scan::query()
      ->whereNotNull('finished_at')
      ->orderByDesc('finished_at')
      ->first();

    return response()->json([
      'active_workers' => $activeWorkers,
      'last_scan_time' => $lastScan?->finished_at,
      'worker_status' => $activeWorkers > 0 ? 'running' : 'stopped',
    ]);
  }
}
