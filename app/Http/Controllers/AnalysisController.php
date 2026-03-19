<?php

namespace App\Http\Controllers;

use App\Models\Analysis;

class AnalysisController extends Controller
{
    public function show(Analysis $localseo)
    {
        $localseo->load([
            'project',
            'reports' => fn($resultQuery) => $resultQuery
                ->orderByDesc('started_at')
                ->orderByDesc('created_at'),
        ]);

        if (auth()->check() && $localseo->project?->user_id !== auth()->id()) {
            abort(403);
        }

        return view('localseo.show', ['localseo' => $localseo]);
    }
}
