<?php

namespace App\Http\Controllers;

use App\Models\Analysis;

class AnalysisController extends Controller
{
    public function show(Analysis $analysis)
    {
        $analysis->load([
            'project',
            'reports' => fn($reportQuery) => $reportQuery
                ->orderByDesc('started_at')
                ->orderByDesc('created_at'),
        ]);

        if (auth()->check() && $analysis->project?->user_id !== auth()->id()) {
            abort(403);
        }

        return view('analyses.show', compact('analysis'));
    }
}
