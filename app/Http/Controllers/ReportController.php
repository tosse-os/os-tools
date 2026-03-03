<?php

namespace App\Http\Controllers;

use App\Models\Report;

class ReportController extends Controller
{
  public function index()
  {
    $query = Report::query();

    if (auth()->check()) {
      $query->where('user_id', auth()->id());
    }

    $reports = $query
      ->latest()
      ->limit(6)
      ->get();

    return view('reports.index', compact('reports'));
  }

  public function archive()
  {
    $query = Report::query();

    if (auth()->check()) {
      $query->where('user_id', auth()->id());
    }

    $reports = $query
      ->latest()
      ->paginate(25);

    return view('reports.archive', compact('reports'));
  }

  public function show(Report $report)
  {
    if (auth()->check() && $report->user_id !== auth()->id()) {
      abort(403);
    }

    $report->load('results');

    return view('reports.show', compact('report'));
  }
}
