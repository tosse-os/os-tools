<?php

namespace App\Http\Controllers;

use App\Models\CrawlLink;
use App\Models\CrawlPage;
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
      ->with('analysis.project')
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
      ->with('analysis.project')
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

    $report->load(['results', 'issues', 'analysis.project']);


    $issuesSummary = [
      'issues_total' => $report->issues->count(),
      'critical_count' => $report->issues->where('severity', 'critical')->count(),
      'warning_count' => $report->issues->where('severity', 'warning')->count(),
    ];

    $report->setAttribute('issues_total', $issuesSummary['issues_total']);
    $report->setAttribute('critical_count', $issuesSummary['critical_count']);
    $report->setAttribute('warning_count', $issuesSummary['warning_count']);

    $issueTypeSummary = $report->issues
      ->groupBy('type')
      ->map(fn($group) => $group->count())
      ->all();

    $timelineQuery = Report::query()->orderBy('started_at');

    if ($report->analysis_id) {
      $timelineQuery->where('analysis_id', $report->analysis_id);
    } else {
      $this->applyContextMatch($timelineQuery, 'url', $report->url);
      $this->applyContextMatch($timelineQuery, 'keyword', $report->keyword);
      $this->applyContextMatch($timelineQuery, 'city', $report->city);
    }

    if (auth()->check()) {
      $timelineQuery->where('user_id', auth()->id());
    }

    $timelineReports = $timelineQuery->get(['id', 'score', 'started_at']);

    $timeline = [
      'labels' => $timelineReports
        ->pluck('started_at')
        ->map(fn($startedAt) => $startedAt ? date('d.m.Y H:i', strtotime((string) $startedAt)) : '—')
        ->values()
        ->all(),
      'data' => $timelineReports
        ->pluck('score')
        ->map(fn($score) => is_numeric($score) ? (float) $score : null)
        ->values()
        ->all(),
    ];

    $currentReportPosition = $timelineReports->search(fn($timelineReport) => $timelineReport->id === $report->id);
    $previousContextReport = is_int($currentReportPosition) && $currentReportPosition > 0
      ? $timelineReports->get($currentReportPosition - 1)
      : null;

    $regression = null;
    $moduleRegressions = [];

    if ($previousContextReport) {
      $latestScore = is_numeric($report->score) ? (float) $report->score : null;
      $previousScore = is_numeric($previousContextReport->score) ? (float) $previousContextReport->score : null;

      if ($latestScore !== null && $previousScore !== null) {
        $delta = $latestScore - $previousScore;
        if ($delta < -10) {
          $regression = [
            'delta' => $delta,
            'drop' => abs($delta),
          ];
        }
      }

      $previousReportModel = Report::with('results')->find($previousContextReport->id);
      $latestBreakdown = $this->extractBreakdown($report);
      $previousBreakdown = $previousReportModel ? $this->extractBreakdown($previousReportModel) : [];

      $trackedModules = [
        'h1' => 'H1',
        'title' => 'Title',
        'content' => 'Content',
        'schema' => 'Schema',
        'nap' => 'Nap',
        'consistency' => 'Consistency',
      ];

      foreach ($trackedModules as $moduleKey => $label) {
        $latestModuleScore = data_get($latestBreakdown, $moduleKey . '.score');
        $previousModuleScore = data_get($previousBreakdown, $moduleKey . '.score');

        if (!is_numeric($latestModuleScore) || !is_numeric($previousModuleScore)) {
          continue;
        }

        $moduleDelta = (float) $latestModuleScore - (float) $previousModuleScore;
        if ($moduleDelta < -3) {
          $moduleRegressions[] = [
            'label' => $label . ' Regression',
            'delta' => $moduleDelta,
            'drop' => abs($moduleDelta),
          ];
        }
      }
    }

    $breakdown = $this->extractBreakdown($report);
    $insights = [];

    $contentScore = data_get($breakdown, 'content.score');
    if (is_numeric($contentScore) && (float) $contentScore < 5) {
      $insights[] = [
        'severity' => 1,
        'title' => 'Content zu schwach',
        'recommendation' => 'Empfehlung: mehr Text und Keyword Integration',
        'score' => (float) $contentScore,
      ];
    }

    $titleScore = data_get($breakdown, 'title.score');
    if (is_numeric($titleScore) && (float) $titleScore < 15) {
      $insights[] = [
        'severity' => 2,
        'title' => 'Title Optimierung möglich',
        'recommendation' => null,
        'score' => (float) $titleScore,
      ];
    }

    $schemaScore = data_get($breakdown, 'schema.score');
    if (is_numeric($schemaScore) && (float) $schemaScore === 0.0) {
      $insights[] = [
        'severity' => 0,
        'title' => 'Schema fehlt',
        'recommendation' => null,
        'score' => (float) $schemaScore,
      ];
    }

    $insights = collect($insights)
      ->sortBy(fn($item) => [$item['severity'], $item['score']])
      ->values()
      ->all();

    $crawlPages = collect();
    $crawlSummary = [
      'pages_crawled' => 0,
      'internal_links' => 0,
      'external_links' => 0,
    ];

    if ($report->type === 'crawler') {
      $crawlPages = CrawlPage::query()
        ->where('crawl_id', $report->id)
        ->orderBy('depth')
        ->orderBy('url')
        ->get([
          'url',
          'depth',
          'status_code',
          'canonical',
          'title',
          'meta_description',
          'h1_count',
          'heading_count',
          'image_count',
          'alt_missing_count',
          'internal_links',
          'external_links',
          'text_hash',
        ]);

      $crawlSummary = [
        'pages_crawled' => $crawlPages->count(),
        'internal_links' => CrawlLink::where('crawl_id', $report->id)->where('link_type', 'internal')->count(),
        'external_links' => CrawlLink::where('crawl_id', $report->id)->where('link_type', 'external')->count(),
      ];
    }

    return view('reports.show', [
      'report' => $report,
      'timeline' => $timeline,
      'regression' => $regression,
      'moduleRegressions' => $moduleRegressions,
      'insights' => $insights,
      'issuesSummary' => $issuesSummary,
      'issueTypeSummary' => $issueTypeSummary,
      'crawlPages' => $crawlPages,
      'crawlSummary' => $crawlSummary,
    ]);
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

    $baseReport = $reports->first();
    $comparisonReport = $reports->last();

    $comparisonRows = collect($comparisonModules)
      ->map(function ($moduleName) use ($comparisonData, $baseReport, $comparisonReport) {
        $baseScore = data_get($comparisonData, $moduleName . '.' . optional($baseReport)->id . '.score');
        $compareScore = data_get($comparisonData, $moduleName . '.' . optional($comparisonReport)->id . '.score');
        $baseMax = data_get($comparisonData, $moduleName . '.' . optional($baseReport)->id . '.max_score');
        $compareMax = data_get($comparisonData, $moduleName . '.' . optional($comparisonReport)->id . '.max_score');

        $delta = is_numeric($baseScore) && is_numeric($compareScore)
          ? (float) $compareScore - (float) $baseScore
          : null;

        return [
          'module' => $moduleName,
          'module_label' => ucfirst($moduleName),
          'base_score' => is_numeric($baseScore) ? (float) $baseScore : null,
          'compare_score' => is_numeric($compareScore) ? (float) $compareScore : null,
          'base_max' => is_numeric($baseMax) ? (float) $baseMax : null,
          'compare_max' => is_numeric($compareMax) ? (float) $compareMax : null,
          'delta' => $delta,
          'delta_text' => $delta === null ? '–' : (($delta > 0 ? '+' : '') . $delta),
          'delta_class' => $delta > 0
            ? 'text-green-600'
            : ($delta < 0 ? 'text-red-600' : 'text-gray-600'),
          'base_bar' => is_numeric($baseScore) ? str_repeat('█', max(1, (int) round((float) $baseScore))) : '',
          'compare_bar' => is_numeric($compareScore) ? str_repeat('█', max(1, (int) round((float) $compareScore))) : '',
        ];
      })
      ->values();

    $largestChange = $comparisonRows
      ->filter(fn($row) => $row['delta'] !== null)
      ->sortByDesc(fn($row) => abs($row['delta']))
      ->first();

    $changedModules = $comparisonRows
      ->filter(fn($row) => $row['delta'] !== null && $row['delta'] != 0)
      ->values();

    return view('reports.compare', [
      'reports' => $reports,
      'comparisonModules' => $comparisonModules,
      'comparisonData' => $comparisonData,
      'scoreDifferences' => $scoreDifferences,
      'mode' => $mode,
      'compareQuery' => ['reports' => $reports->pluck('id')->all()],
      'hasContextMismatch' => $contextKeys->count() > 1,
      'baseReport' => $baseReport,
      'comparisonReport' => $comparisonReport,
      'comparisonRows' => $comparisonRows,
      'largestChange' => $largestChange,
      'changedModules' => $changedModules,
    ]);
  }

  private function applyContextMatch($query, string $column, $value): void
  {
    $normalized = trim((string) ($value ?? ''));

    if ($normalized === '') {
      $query->where(function ($innerQuery) use ($column) {
        $innerQuery->whereNull($column)->orWhere($column, '');
      });

      return;
    }

    $query->where($column, $normalized);
  }

  private function extractBreakdown(Report $report): array
  {
    foreach ($report->results as $result) {
      $payload = is_array($result->payload)
        ? $result->payload
        : json_decode((string) $result->payload, true);

      if (is_array($payload) && is_array(data_get($payload, 'breakdown'))) {
        return $payload['breakdown'];
      }
    }

    return [];
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
          'project' => $this->valueOrDash(data_get($latestReport, 'analysis.project.name')),
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
    if (!empty($report->analysis_id)) {
      return (string) $report->analysis_id;
    }

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
