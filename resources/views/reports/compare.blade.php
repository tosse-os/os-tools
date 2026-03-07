@extends('layouts.app')

@section('content')
@php
  use Carbon\Carbon;

  $reportList = collect($reports ?? [])->take(4)->values();
  $reportSummaries = $reportList->map(function ($report) use ($comparisonModules, $comparisonData) {
    $startedAt = null;

    if (!blank($report->started_at)) {
      try {
        $startedAt = Carbon::parse($report->started_at);
      } catch (\Throwable $e) {
        $startedAt = null;
      }
    }

    $overallScore = 0;
    $overallMax = 0;

    foreach ($comparisonModules as $moduleName) {
      $module = $comparisonData[$moduleName][$report->id] ?? [];
      $overallScore += (int) ($module['score'] ?? 0);
      $overallMax += (int) ($module['max_score'] ?? 0);
    }

    $reportScore = isset($report->score) && is_numeric($report->score) ? (float) $report->score : 0.0;
    $scoreValue = $overallMax > 0 ? (float) $overallScore : $reportScore;

    return [
      'id' => $report->id,
      'keyword' => $report->keyword ?: '—',
      'city' => $report->city ?: '—',
      'domain' => parse_url((string) ($report->url ?? ''), PHP_URL_HOST) ?: '—',
      'started_at' => $startedAt,
      'score' => $scoreValue,
    ];
  });
@endphp

<div class="max-w-7xl mx-auto bg-white shadow-sm rounded-lg p-8 space-y-6 border border-gray-100">
  <div class="flex justify-between items-center gap-3">
    <h1 class="text-2xl font-semibold">Report Comparison</h1>
    <a href="{{ url()->previous() }}" class="text-sm text-blue-600 hover:underline">Back</a>
  </div>

  <div class="flex items-center gap-3 text-sm">
    <a href="{{ route('reports.compare', array_merge($compareQuery, ['mode' => 'modules'])) }}"
      class="rounded-lg px-3 py-1.5 {{ $mode === 'modules' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">
      Module Comparison
    </a>

    <a href="{{ route('reports.compare', array_merge($compareQuery, ['mode' => 'delta'])) }}"
      class="rounded-lg px-3 py-1.5 {{ $mode === 'delta' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">
      Score Difference
    </a>
  </div>

  @if($hasContextMismatch)
    <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
      These reports use different keyword or city parameters.<br>
      Comparison may be misleading.
    </div>
  @endif

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    @foreach($reportSummaries as $summary)
      <div class="rounded-lg border border-gray-200 p-4 shadow-sm bg-gray-50">
        <div class="font-semibold text-gray-900">{{ $summary['keyword'] }} • {{ $summary['city'] }}</div>
        <div class="text-sm text-gray-700">{{ $summary['domain'] }}</div>
        <div class="text-xs text-gray-500">{{ $summary['started_at'] ? $summary['started_at']->format('d.m.Y H:i') : '—' }}</div>
        <div class="mt-2 text-sm font-semibold text-gray-900">Score {{ number_format($summary['score'], 0) }}</div>
      </div>
    @endforeach
  </div>

  @if($mode === 'delta')
    <div class="rounded-lg shadow border border-gray-200 p-6 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-100 text-left">
          <tr>
            <th class="px-4 py-3 border-b">Module</th>
            <th class="px-4 py-3 border-b">Difference</th>
            @foreach($reportSummaries as $summary)
              <th class="px-4 py-3 border-b whitespace-nowrap">
                {{ $summary['started_at'] ? $summary['started_at']->format('d M') : '—' }}
              </th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($comparisonModules as $module)
            <tr>
              <td class="px-4 py-3 border-b font-semibold">{{ ucfirst($module) }}</td>
              <td class="px-4 py-3 border-b {{ data_get($scoreDifferences, $module . '.difference_class', 'text-gray-600') }}">
                {{ data_get($scoreDifferences, $module . '.difference_text', '–') }}
              </td>
              @foreach($reportSummaries as $summary)
                <td class="px-4 py-3 border-b">
                  {{ data_get($scoreDifferences, $module . '.scores.' . $summary['id'], '—') }}
                </td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="rounded-lg shadow border border-gray-200 p-6 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-100 text-left">
          <tr>
            <th class="px-4 py-3 border-b">Module</th>
            @foreach($reportSummaries as $summary)
              <th class="px-4 py-3 border-b whitespace-nowrap">
                <div class="font-medium text-gray-900">{{ $summary['started_at'] ? $summary['started_at']->format('d M') : '—' }}</div>
                <div class="text-xs text-gray-500">{{ number_format($summary['score'], 0) }}/100</div>
              </th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($comparisonModules as $module)
            <tr>
              <td class="px-4 py-3 border-b font-semibold">{{ ucfirst($module) }}</td>
              @foreach($reportSummaries as $summary)
                @php
                  $moduleData = $comparisonData[$module][$summary['id']] ?? [];
                  $score = (int) ($moduleData['score'] ?? 0);
                  $max = (int) ($moduleData['max_score'] ?? 0);
                @endphp
                <td class="px-4 py-3 border-b">{{ $score }}{{ $max > 0 ? ' / ' . $max : '' }}</td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

@endsection
