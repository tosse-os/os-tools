<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\Storage;

class ReportPersistenceService
{
  public function syncFromStorage(Report $report): void
  {
    $directory = "scans/{$report->id}";

    if (!Storage::exists($directory)) {
      return;
    }

    $files = collect(Storage::files($directory))
      ->filter(fn(string $path) => str_ends_with($path, '.json'))
      ->reject(fn(string $path) => str_ends_with($path, 'progress.json'))
      ->sort()
      ->values();

    if ($files->isEmpty()) {
      return;
    }

    $module = $report->type === 'crawler' ? 'crawler' : 'local_seo';
    $scores = [];

    foreach ($files as $position => $path) {
      $content = json_decode(Storage::get($path), true);

      if (!is_array($content)) {
        continue;
      }

      $score = null;
      if (isset($content['score']) && is_numeric($content['score'])) {
        $score = (float) $content['score'];
        $scores[] = $score;
      }

      $report->results()->updateOrCreate(
        [
          'report_id' => $report->id,
          'position' => (int) $position,
        ],
        [
          'module' => $module,
          'url' => $content['url'] ?? $report->url,
          'score' => $score,
          'payload' => $content,
        ]
      );
    }

    $progressPath = "{$directory}/progress.json";
    $progress = Storage::exists($progressPath)
      ? json_decode(Storage::get($progressPath), true)
      : [];

    $finalScore = !empty($scores)
      ? round(array_sum($scores) / count($scores), 2)
      : ($report->score ?? 0);

    $report->update([
      'score' => $finalScore,
      'status' => 'done',
      'total_urls' => $progress['total'] ?? $report->total_urls,
      'processed_urls' => $progress['current'] ?? $report->processed_urls,
      'finished_at' => now(),
    ]);
  }
}
