<?php

namespace App\Http\Controllers;

use App\Models\Scan;
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

    $workerSnapshot = $this->workerSnapshot();

    $activeScans = Scan::where('status', 'running')->count();
    $queuedScans = Scan::whereIn('status', ['queued', 'pending'])->count();
    $failedScans = Scan::where('status', 'failed')->count();

    return view('queues.index', compact(
      'jobs',
      'failedJobs',
      'workerSnapshot',
      'activeScans',
      'queuedScans',
      'failedScans'
    ));
  }

  public function workers()
  {
    $workerSnapshot = $this->workerSnapshot();

    $lastScan = Scan::whereNotNull('finished_at')
      ->latest('finished_at')
      ->first();

    return response()->json([
      'active_workers' => $workerSnapshot['active_workers'],
      'last_scan_time' => optional($lastScan?->finished_at)->toISOString(),
      'worker_status' => $workerSnapshot['status'],
    ]);
  }

  private function workerSnapshot(): array
  {
    $snapshotPath = storage_path('app/scan-workers.json');

    if (!is_file($snapshotPath)) {
      return [
        'active_workers' => 0,
        'status' => 'inactive',
      ];
    }

    $snapshot = json_decode(file_get_contents($snapshotPath), true) ?: [];

    $heartbeat = data_get($snapshot, 'heartbeat');
    $lastHeartbeat = $heartbeat ? strtotime($heartbeat) : null;
    $isHealthy = $lastHeartbeat && (time() - $lastHeartbeat) <= 30;

    return [
      'active_workers' => (int) data_get($snapshot, 'active_workers', 0),
      'status' => $isHealthy ? 'running' : 'stale',
    ];
  }
}
