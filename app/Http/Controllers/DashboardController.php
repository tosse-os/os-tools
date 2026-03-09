<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Scan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        $query = Report::query();

        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        }

        $latestReports = $query
            ->latest()
            ->limit(8)
            ->get();

        $queuedJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $activeJobs = $queuedJobs;
        if (Schema::hasColumn('jobs', 'reserved_at')) {
            $activeJobs = DB::table('jobs')
                ->whereNotNull('reserved_at')
                ->count();
        }

        $runningScans = Scan::where('status', 'running')->count();
        $failedScans = Scan::where('status', 'failed')->count();

        return view('dashboard.index', compact(
            'latestReports',
            'activeJobs',
            'queuedJobs',
            'failedJobs',
            'runningScans',
            'failedScans'
        ));
    }
}
