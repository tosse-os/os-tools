<?php

namespace App\Http\Controllers;

use App\Models\Report;

class DashboardController extends Controller
{
    public function index()
    {
        $query = \App\Models\Report::query();

        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        }

        $latestReports = $query
            ->latest()
            ->limit(8)
            ->get();

        return view('dashboard.index', compact('latestReports'));
    }
}
