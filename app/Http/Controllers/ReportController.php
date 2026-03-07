<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
  public function index(Request $request)
  {
    $query = Report::query();

    if (auth()->check()) {
      $query->where('user_id', auth()->id());
    }

    $this->applyArchiveFilters($query, $request);

    $reports = $query
      ->latest()
      ->limit(60)
      ->get();

    $reportContexts = $this->buildReportContexts($reports);

    return view('reports.index', [
      'reportContexts' => $reportContexts,
      'filters' => $this->extractFilters($request),
    ]);
  }

  public function archive(Request $request)
  {
    $query = Report::query();

    if (auth()->check()) {
      $query->where('user_id', auth()->id());
    }

    $this->applyArchiveFilters($query, $request);

    $reports = $query
      ->latest()
      ->get();

    $reportContexts = $this->buildReportContexts($reports);

    return view('reports.archive', [
      'reportContexts' => $reportContexts,
      'filters' => $this->extractFilters($request),
    ]);
  }

  public function show(Report $report)
  {
    if (auth()->check() && $report->user_id !== auth()->id()) {
      abort(403);
    }

    $report->load('results');

    return view('reports.show', compact('report'));
  }

  public function compare(Request $request)
  {
    $ids = collect($request->input('reports', []))
      ->filter()
      ->unique()
      ->values();

    if ($ids->count() < 2) {
      return redirect()->back()->withErrors([
        'reports' => 'Bitte mindestens 2 Reports für den Vergleich auswählen.',
      ])->withInput();
    }

    if ($ids->count() > 4) {
      return redirect()->back()->withErrors([
        'reports' => 'Bitte maximal 4 Reports für den Vergleich auswählen.',
      ])->withInput();
    }

    $reports = Report::with('results')
      ->whereIn('id', $ids)
      ->get()
      ->sortBy(fn($report) => $ids->search($report->id))
      ->values();

    if ($reports->count() !== $ids->count()) {
      return redirect()->back()->withErrors([
        'reports' => 'Mindestens ein ausgewählter Report wurde nicht gefunden.',
      ])->withInput();
    }

    if (auth()->check() && $reports->contains(fn($report) => $report->user_id !== auth()->id())) {
      abort(403);
    }

    $comparisonModules = [];
    $comparisonData = [];

    foreach ($reports as $report) {
      $reportBreakdown = [];

      foreach ($report->results as $result) {
        $payload = is_array($result->payload)
          ? $result->payload
          : json_decode((string) $result->payload, true);

        if (!is_array($payload) || !is_array(data_get($payload, 'breakdown'))) {
          continue;
        }

        foreach ($payload['breakdown'] as $moduleName => $moduleData) {
          if (!isset($reportBreakdown[$moduleName])) {
            $reportBreakdown[$moduleName] = $moduleData;
          }
        }
      }

      foreach ($reportBreakdown as $moduleName => $moduleData) {
        $comparisonModules[$moduleName] = $moduleName;

        $checks = [];
        foreach ((array) data_get($moduleData, 'checks', []) as $checkKey => $checkValue) {
          $checks[] = [
            'label' => is_string($checkKey) ? $checkKey : (string) $checkValue,
            'passed' => (bool) $checkValue,
          ];
        }

        $comparisonData[$moduleName][$report->id] = [
          'score' => data_get($moduleData, 'score'),
          'max_score' => data_get($moduleData, 'max_score'),
          'missing' => array_values((array) data_get($moduleData, 'missing', [])),
          'checks' => $checks,
        ];
      }
    }

    $comparisonModules = collect($comparisonModules)->values()->all();

    $scoreDifferences = [];

    foreach ($comparisonModules as $moduleName) {
      $moduleScores = [];

      foreach ($reports as $report) {
        $moduleScores[$report->id] = data_get($comparisonData, "{$moduleName}.{$report->id}.score");
      }

      $firstReportId = optional($reports->first())->id;
      $lastReportId = optional($reports->last())->id;
      $firstScore = $firstReportId ? ($moduleScores[$firstReportId] ?? null) : null;
      $lastScore = $lastReportId ? ($moduleScores[$lastReportId] ?? null) : null;
      $difference = is_numeric($firstScore) && is_numeric($lastScore)
        ? $lastScore - $firstScore
        : null;

      $scoreDifferences[$moduleName] = [
        'scores' => $moduleScores,
        'difference' => $difference,
        'difference_text' => $difference === null ? '–' : (($difference > 0 ? '+' : '') . $difference),
        'difference_class' => $difference > 0
          ? 'text-green-600'
          : ($difference < 0 ? 'text-red-600' : 'text-gray-600'),
      ];
    }

    $mode = $request->query('mode', 'modules');
    if (!in_array($mode, ['modules', 'delta'], true)) {
      $mode = 'modules';
    }

    $contextKeys = $reports->map(function ($report) {
      return $this->buildContextKey($report);
    })->unique()->values();

    return view('reports.compare', [
      'reports' => $reports,
      'comparisonModules' => $comparisonModules,
      'comparisonData' => $comparisonData,
      'scoreDifferences' => $scoreDifferences,
      'mode' => $mode,
      'compareQuery' => ['reports' => $reports->pluck('id')->all()],
      'hasContextMismatch' => $contextKeys->count() > 1,
    ]);
  }

  private function applyArchiveFilters($query, Request $request): void
  {
    $keyword = trim((string) $request->query('keyword', ''));
    if ($keyword !== '') {
      $query->where('keyword', 'like', '%' . $keyword . '%');
    }

    $city = trim((string) $request->query('city', ''));
    if ($city !== '') {
      $query->where('city', 'like', '%' . $city . '%');
    }

    $domain = trim((string) $request->query('domain', ''));
    if ($domain !== '') {
      $query->where('url', 'like', '%' . $domain . '%');
    }
  }

  private function extractFilters(Request $request): array
  {
    return [
      'keyword' => trim((string) $request->query('keyword', '')),
      'city' => trim((string) $request->query('city', '')),
      'domain' => trim((string) $request->query('domain', '')),
    ];
  }

  private function buildReportContexts($reports)
  {
    return $reports
      ->groupBy(fn($report) => $this->buildContextKey($report))
      ->map(function ($contextReports, $contextKey) {
        $latestReport = $contextReports->sortByDesc('started_at')->first();

        return [
          'context_key' => $contextKey,
          'keyword' => $this->valueOrDash(optional($latestReport)->keyword),
          'city' => $this->valueOrDash(optional($latestReport)->city),
          'domain' => $this->extractDomain(optional($latestReport)->url),
          'url' => optional($latestReport)->url,
          'reports_count' => $contextReports->count(),
          'last_score' => is_numeric(optional($latestReport)->score) ? (float) $latestReport->score : null,
          'latest_started_at' => optional($latestReport)->started_at,
          'reports' => $contextReports->values(),
        ];
      })
      ->sortByDesc(function ($group) {
        $startedAt = $group['latest_started_at'];

        if ($startedAt instanceof \DateTimeInterface) {
          return $startedAt->getTimestamp();
        }

        if ($startedAt && strtotime((string) $startedAt) !== false) {
          return strtotime((string) $startedAt);
        }

        return PHP_INT_MIN;
      })
      ->values();
  }

  private function buildContextKey(Report $report): string
  {
    $url = trim((string) ($report->url ?? ''));
    $keyword = $this->valueOrDash($report->keyword);
    $city = $this->valueOrDash($report->city);

    return implode('|', [$url !== '' ? $url : '—', $keyword, $city]);
  }

  private function valueOrDash($value): string
  {
    $normalized = trim((string) ($value ?? ''));

    return $normalized !== '' ? $normalized : '—';
  }

  private function extractDomain($url): string
  {
    $rawUrl = trim((string) ($url ?? ''));

    if ($rawUrl === '') {
      return '—';
    }

    return parse_url($rawUrl, PHP_URL_HOST) ?: $rawUrl;
  }
}
