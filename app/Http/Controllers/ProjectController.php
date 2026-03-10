<?php

namespace App\Http\Controllers;

use App\Models\Project;

class ProjectController extends Controller
{
    public function index()
    {
        $query = Project::query()
            ->withCount('analyses')
            ->with([
                'analyses.reports' => fn($reportQuery) => $reportQuery
                    ->select('id', 'analysis_id', 'started_at')
                    ->orderByDesc('started_at'),
            ])
            ->orderBy('name');

        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        }

        $projects = $query->get();

        return view('projects.index', compact('projects'));
    }

    public function show(Project $project)
    {
        if (auth()->check() && $project->user_id !== auth()->id()) {
            abort(403);
        }

        $project->load([
            'analyses' => fn($analysisQuery) => $analysisQuery
                ->withCount('reports')
                ->with([
                    'reports' => fn($reportQuery) => $reportQuery
                        ->select('id', 'analysis_id', 'score', 'started_at')
                        ->orderByDesc('started_at'),
                ])
                ->orderBy('keyword')
                ->orderBy('city'),
        ]);

        return view('projects.show', compact('project'));
    }
}
