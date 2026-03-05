<?php

namespace App\Http\Controllers;

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
      ->select('id', 'exception')
      ->orderByDesc('id')
      ->get();

    return view('queues.index', compact('jobs', 'failedJobs'));
  }
}
