<?php

namespace App\Services;

use App\Models\Report;
use App\Models\Issue;
use Illuminate\Support\Facades\Storage;

class ReportPersistenceService
{
  public function syncFromStorage(Report $report): void
  {
    $directory = "scans/{$report->id}";

    if (!Storage::exists($directory)) {
      return;
    }

    $pages = $this->loadPages($directory);

    if (empty($pages)) {
      return;
    }

    $module = $report->type === 'crawler' ? 'crawler' : 'local_seo';
    $scores = [];

    $report->results()->delete();

    foreach ($pages as $position => $content) {

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

    $summary = $this->buildSummary($pages);
    Storage::put("{$directory}/pages.json", json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    Storage::put("{$directory}/summary.json", json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $report->results()->updateOrCreate(
      [
        'report_id' => $report->id,
        'key' => 'summary',
      ],
      [
        'module' => 'crawler',
        'value' => json_encode($summary, JSON_UNESCAPED_SLASHES),
        'payload' => $summary,
      ]
    );

    foreach ($summary as $key => $value) {
      $report->results()->updateOrCreate(
        [
          'report_id' => $report->id,
          'key' => $key,
        ],
        [
          'module' => 'crawler',
          'value' => (string) $value,
          'payload' => ['value' => $value],
        ]
      );
    }

    $issues = app(IssueDetectionService::class)->detectFromReportResults($pages);
    $report->issues()->delete();
    if (!empty($issues)) {
      $report->issues()->createMany(array_map(function (array $issue) use ($report) {
        return [
          'report_id' => $report->id,
          'url' => $issue['url'] ?? $report->url,
          'type' => $issue['type'],
          'severity' => $issue['severity'] ?? Issue::SEVERITY_INFO,
          'message' => $issue['message'],
          'created_at' => now(),
        ];
      }, $issues));
    }
    Storage::put("{$directory}/issues.json", json_encode($issues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

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

  private function loadPages(string $directory): array
  {
    $pagesPath = "{$directory}/pages.json";
    if (Storage::exists($pagesPath)) {
      $pages = json_decode((string) Storage::get($pagesPath), true);
      return is_array($pages) ? array_values(array_filter($pages, 'is_array')) : [];
    }

    $files = collect(Storage::files($directory))
      ->filter(fn(string $path) => str_ends_with($path, '.json'))
      ->reject(fn(string $path) => str_ends_with($path, 'progress.json'))
      ->reject(fn(string $path) => str_ends_with($path, 'issues.json'))
      ->reject(fn(string $path) => str_ends_with($path, 'summary.json'))
      ->sort()
      ->values();

    return $files
      ->map(fn(string $path) => json_decode((string) Storage::get($path), true))
      ->filter(fn($content) => is_array($content))
      ->values()
      ->all();
  }

  private function buildSummary(array $pages): array
  {
    $summary = [
      'pages_total' => count($pages),
      'status_200' => 0,
      'status_404' => 0,
      'missing_alt' => 0,
      'missing_h1' => 0,
      'multiple_h1' => 0,
      'broken_links' => 0,
    ];

    foreach ($pages as $page) {
      $status = (int) data_get($page, 'statusCheck.status', 0);
      if ($status === 200) {
        $summary['status_200']++;
      }
      if ($status === 404) {
        $summary['status_404']++;
      }

      $summary['missing_alt'] += (int) data_get($page, 'altCheck.altMissing', 0) + (int) data_get($page, 'altCheck.altEmpty', 0);

      $h1Count = (int) data_get($page, 'headingCheck.count.h1', 0);
      if ($h1Count < 1) {
        $summary['missing_h1']++;
      }
      if ($h1Count > 1) {
        $summary['multiple_h1']++;
      }

      if ($status >= 400) {
        $summary['broken_links']++;
      }
    }

    return $summary;
  }
}
