<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\Storage;

class ReportPersistenceService
{
  public function syncFromStorage(Report $report): void
  {
    $path = "scans/{$report->id}/0.json";

    if (!Storage::exists($path)) {
      return;
    }

    $content = json_decode(Storage::get($path), true);

    if (!isset($content['score'])) {
      return;
    }

    $report->update([
      'score' => $content['score']['score'] ?? 0,
      'status' => 'done',
      'finished_at' => now(),
    ]);
  }
}
