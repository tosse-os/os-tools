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

  @if($changedModules->count() > 0)
  <div class="rounded-lg border border-blue-200 bg-blue-50 p-5">
    <h2 class="text-lg font-semibold text-blue-900 mb-2">Modules with changes</h2>
    <div class="flex flex-wrap gap-2 text-sm">
      @foreach($changedModules as $module)
      <div class="rounded border border-blue-200 bg-white px-3 py-1 {{ $module['delta_class'] }}">
        {{ $module['module_label'] }} {{ $module['delta_text'] }}
      </div>
      @endforeach
    </div>
  </div>
  @endif

  @if($largestChange)
  <div class="rounded-lg border border-purple-200 bg-purple-50 p-4 text-sm">
    <div class="font-semibold text-purple-900">Größte Veränderung</div>
    <div class="text-purple-800">{{ $largestChange['module_label'] }} {{ $largestChange['delta_text'] }}</div>
  </div>
  @endif

  @if($baseReport && $comparisonReport)
  <div class="rounded-lg border border-gray-200 p-5 space-y-2">
    <h2 class="text-lg font-semibold">Visual Module Comparison</h2>
    @foreach($comparisonRows as $row)
    <div class="text-sm border-b border-gray-100 pb-2">
      <div class="font-medium text-gray-900">{{ $row['module_label'] }}</div>
      <div class="text-xs text-gray-600">
        {{ optional($baseReport->started_at)->format('d M') ?: 'A' }} {{ $row['base_bar'] ?: '—' }}
      </div>
      <div class="text-xs text-gray-600">
        {{ optional($comparisonReport->started_at)->format('d M') ?: 'B' }} {{ $row['compare_bar'] ?: '—' }}
      </div>
    </div>
    @endforeach
  </div>
  @endif

  <div class="rounded-lg shadow border border-gray-200 p-6 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-100 text-left">
        <tr>
          <th class="px-4 py-3 border-b">Module</th>
          <th class="px-4 py-3 border-b">Version A</th>
          <th class="px-4 py-3 border-b">Version B</th>
          <th class="px-4 py-3 border-b">Delta</th>
        </tr>
      </thead>
      <tbody>
        @foreach($comparisonRows as $row)
          @php
            $isLargest = $largestChange && $largestChange['module'] === $row['module'];
          @endphp
          <tr class="{{ $isLargest ? 'bg-purple-50' : '' }}">
            <td class="px-4 py-3 border-b font-semibold">{{ $row['module_label'] }}</td>
            <td class="px-4 py-3 border-b">{{ is_numeric($row['base_score']) ? number_format($row['base_score'], 0) : '—' }}</td>
            <td class="px-4 py-3 border-b">{{ is_numeric($row['compare_score']) ? number_format($row['compare_score'], 0) : '—' }}</td>
            <td class="px-4 py-3 border-b font-semibold {{ $row['delta_class'] }}">{{ $row['delta_text'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

@endsection
