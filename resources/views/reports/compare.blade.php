@extends('layouts.app')

@section('content')
@php
$reportA = $reports[0] ?? null;
$reportB = $reports[1] ?? null;
$reportAId = $reportA->id ?? null;
$reportBId = $reportB->id ?? null;

$overallScoreA = 0;
$overallMaxA = 0;
$overallScoreB = 0;
$overallMaxB = 0;

foreach ($comparisonModules as $moduleName) {
$moduleA = $comparisonData[$moduleName][$reportAId] ?? [];
$moduleB = $comparisonData[$moduleName][$reportBId] ?? [];

$overallScoreA += (int) ($moduleA['score'] ?? 0);
$overallMaxA += (int) ($moduleA['max_score'] ?? 0);
$overallScoreB += (int) ($moduleB['score'] ?? 0);
$overallMaxB += (int) ($moduleB['max_score'] ?? 0);
}

$overallPercentA = $overallMaxA > 0 ? (int) round(($overallScoreA / $overallMaxA) * 100) : 0;
$overallPercentB = $overallMaxB > 0 ? (int) round(($overallScoreB / $overallMaxB) * 100) : 0;
$overallDelta = $overallScoreA - $overallScoreB;
@endphp

<div class="max-w-7xl mx-auto bg-white shadow-sm rounded-lg p-8 space-y-6 border border-gray-100">
  <div class="flex justify-between items-center">
    <h1 class="text-2xl font-semibold">Report Comparison</h1>
    <a href="{{ url()->previous() }}" class="text-sm text-blue-600 hover:underline">Back</a>
  </div>

  <div class="flex items-center gap-3 text-sm">
    <a href="{{ route('reports.compare', array_merge($compareQuery, ['mode' => 'modules'])) }}"
      class="rounded px-3 py-1.5 {{ $mode === 'modules' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">
      Module Comparison
    </a>

    <a href="{{ route('reports.compare', array_merge($compareQuery, ['mode' => 'delta'])) }}"
      class="rounded px-3 py-1.5 {{ $mode === 'delta' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">
      Score Difference
    </a>
  </div>

  <div class="rounded-lg border border-gray-200 p-5 bg-gray-50 space-y-4">
    <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Overall Comparison</h2>
        <p class="text-sm text-gray-600">Comparing two scans of the same URL: Version A vs Version B.</p>
      </div>
      <div class="text-sm font-medium">
        @if($overallDelta > 0)
        <span class="text-green-700">🏆 Version A performs better (+{{ $overallDelta }})</span>
        @elseif($overallDelta < 0)
        <span class="text-green-700">🏆 Version B performs better (+{{ abs($overallDelta) }})</span>
        @else
        <span class="text-gray-600">No difference between versions</span>
        @endif
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="rounded-md border {{ $overallDelta > 0 ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white' }} p-4 space-y-2">
        <div class="flex justify-between items-center">
          <span class="font-semibold text-gray-900">Version A</span>
          <span class="text-sm font-semibold text-gray-700">{{ $overallScoreA }} / {{ $overallMaxA }}</span>
        </div>
        <div class="w-full h-2 rounded bg-gray-200 overflow-hidden">
          <div class="h-2 rounded bg-green-500" style="width: {{ $overallPercentA }}%"></div>
        </div>
        <div class="text-xs text-gray-500">{{ $overallPercentA }}%</div>
      </div>

      <div class="rounded-md border {{ $overallDelta < 0 ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white' }} p-4 space-y-2">
        <div class="flex justify-between items-center">
          <span class="font-semibold text-gray-900">Version B</span>
          <span class="text-sm font-semibold text-gray-700">{{ $overallScoreB }} / {{ $overallMaxB }}</span>
        </div>
        <div class="w-full h-2 rounded bg-gray-200 overflow-hidden">
          <div class="h-2 rounded bg-green-500" style="width: {{ $overallPercentB }}%"></div>
        </div>
        <div class="text-xs text-gray-500">{{ $overallPercentB }}%</div>
      </div>
    </div>
  </div>

  @if($mode === 'delta')
  <div class="rounded-lg border border-gray-200 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-100 text-left">
        <tr>
          <th class="px-4 py-3 border-b">Module</th>
          <th class="px-4 py-3 border-b">Version A</th>
          <th class="px-4 py-3 border-b">Version B</th>
          <th class="px-4 py-3 border-b">Difference</th>
        </tr>
      </thead>
      <tbody>
        @foreach($comparisonModules as $module)
        @php
        $moduleA = $comparisonData[$module][$reportAId] ?? [];
        $moduleB = $comparisonData[$module][$reportBId] ?? [];
        $moduleScoreA = (int) ($moduleA['score'] ?? 0);
        $moduleScoreB = (int) ($moduleB['score'] ?? 0);
        $moduleDelta = $moduleScoreA - $moduleScoreB;
        @endphp
        <tr>
          <td class="px-4 py-3 border-b font-semibold align-top">{{ ucfirst($module) }}</td>
          <td class="px-4 py-3 border-b align-top">{{ $moduleScoreA }}</td>
          <td class="px-4 py-3 border-b align-top">{{ $moduleScoreB }}</td>
          <td class="px-4 py-3 border-b align-top font-semibold {{ $moduleDelta > 0 ? 'text-green-700' : ($moduleDelta < 0 ? 'text-red-700' : 'text-gray-600') }}">
            {{ $moduleDelta > 0 ? '+' . $moduleDelta . ' better' : ($moduleDelta < 0 ? $moduleDelta . ' worse' : 'No change') }}
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @else
  <div class="space-y-4">
    @foreach($comparisonModules as $module)
    @php
    $moduleA = $comparisonData[$module][$reportAId] ?? [];
    $moduleB = $comparisonData[$module][$reportBId] ?? [];

    $scoreA = (int) ($moduleA['score'] ?? 0);
    $maxA = (int) ($moduleA['max_score'] ?? 0);
    $scoreB = (int) ($moduleB['score'] ?? 0);
    $maxB = (int) ($moduleB['max_score'] ?? 0);

    $percentA = $maxA > 0 ? (int) round(($scoreA / $maxA) * 100) : 0;
    $percentB = $maxB > 0 ? (int) round(($scoreB / $maxB) * 100) : 0;

    $delta = $scoreA - $scoreB;

    $scoreColorA = $percentA <= 30 ? 'text-red-700' : ($percentA <= 70 ? 'text-yellow-700' : 'text-green-700');
    $scoreColorB = $percentB <= 30 ? 'text-red-700' : ($percentB <= 70 ? 'text-yellow-700' : 'text-green-700');

    $barColorA = $percentA <= 30 ? 'bg-red-500' : ($percentA <= 70 ? 'bg-yellow-500' : 'bg-green-500');
    $barColorB = $percentB <= 30 ? 'bg-red-500' : ($percentB <= 70 ? 'bg-yellow-500' : 'bg-green-500');

    $badgeLabel = $percentA >= $percentB ? ($percentA >= 71 ? '🟢 Excellent' : ($percentA >= 31 ? '🟡 Moderate' : '🔴 Missing')) : ($percentB >= 71 ? '🟢 Excellent' : ($percentB >= 31 ? '🟡 Moderate' : '🔴 Missing'));
    @endphp

    <div class="rounded-lg shadow border border-gray-200 p-4 space-y-4" x-data="{ open: false }">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2">
        <div class="flex items-center gap-3 flex-wrap">
          <h3 class="text-lg font-semibold text-gray-900 uppercase tracking-wide">{{ ucfirst($module) }}</h3>
          <span class="text-xs font-semibold px-2 py-1 rounded-full bg-gray-100 text-gray-700">{{ $badgeLabel }}</span>
          <span class="text-sm font-semibold {{ $delta > 0 ? 'text-green-700' : ($delta < 0 ? 'text-red-700' : 'text-gray-600') }}">
            Difference: {{ $delta > 0 ? '+' . $delta . ' better' : ($delta < 0 ? $delta . ' worse' : 'No change') }}
          </span>
        </div>
        <div class="text-sm">
          @if($delta > 0)
          <span class="text-green-700 font-medium">🏆 Version A performs better</span>
          @elseif($delta < 0)
          <span class="text-green-700 font-medium">🏆 Version B performs better</span>
          @else
          <span class="text-gray-600">No difference between versions</span>
          @endif
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-md border p-4 space-y-3 {{ $delta > 0 ? 'bg-green-50 border-green-300' : 'bg-white border-gray-200' }}">
          <div class="flex justify-between items-center">
            <span class="font-semibold text-gray-900">Version A</span>
            <span class="font-semibold {{ $scoreColorA }}">{{ $scoreA }} / {{ $maxA }}</span>
          </div>
          <div class="w-full bg-gray-200 h-2 rounded overflow-hidden">
            <div class="{{ $barColorA }} h-2 rounded" style="width: {{ $percentA }}%"></div>
          </div>
          <div class="text-xs text-gray-500">{{ $percentA }}%</div>
        </div>

        <div class="rounded-md border p-4 space-y-3 {{ $delta < 0 ? 'bg-green-50 border-green-300' : 'bg-white border-gray-200' }}">
          <div class="flex justify-between items-center">
            <span class="font-semibold text-gray-900">Version B</span>
            <span class="font-semibold {{ $scoreColorB }}">{{ $scoreB }} / {{ $maxB }}</span>
          </div>
          <div class="w-full bg-gray-200 h-2 rounded overflow-hidden">
            <div class="{{ $barColorB }} h-2 rounded" style="width: {{ $percentB }}%"></div>
          </div>
          <div class="text-xs text-gray-500">{{ $percentB }}%</div>
        </div>
      </div>

      <div>
        <button type="button" class="text-sm text-blue-600 hover:underline" @click="open = !open" x-text="open ? 'Hide details' : 'Show details'"></button>

        <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4" x-show="open" x-cloak>
          <div class="rounded-md border border-gray-200 p-4 space-y-3 bg-gray-50">
            <div class="text-sm font-semibold text-gray-900">Version A Checks</div>
            <ul class="space-y-1 text-sm">
              @forelse($moduleA['checks'] ?? [] as $check)
              <li class="flex items-start gap-2 {{ $check['passed'] ? 'text-green-700' : 'text-yellow-700' }}">
                <span>{{ $check['passed'] ? '✔' : '⚠' }}</span>
                <span>{{ $check['label'] }}</span>
              </li>
              @empty
              <li class="text-gray-500">–</li>
              @endforelse

              @foreach($moduleA['missing'] ?? [] as $missing)
              <li class="flex items-start gap-2 text-red-700">
                <span>✖</span>
                <span>{{ $missing }}</span>
              </li>
              @endforeach
            </ul>
          </div>

          <div class="rounded-md border border-gray-200 p-4 space-y-3 bg-gray-50">
            <div class="text-sm font-semibold text-gray-900">Version B Checks</div>
            <ul class="space-y-1 text-sm">
              @forelse($moduleB['checks'] ?? [] as $check)
              <li class="flex items-start gap-2 {{ $check['passed'] ? 'text-green-700' : 'text-yellow-700' }}">
                <span>{{ $check['passed'] ? '✔' : '⚠' }}</span>
                <span>{{ $check['label'] }}</span>
              </li>
              @empty
              <li class="text-gray-500">–</li>
              @endforelse

              @foreach($moduleB['missing'] ?? [] as $missing)
              <li class="flex items-start gap-2 text-red-700">
                <span>✖</span>
                <span>{{ $missing }}</span>
              </li>
              @endforeach
            </ul>
          </div>
        </div>
      </div>
    </div>
    @endforeach
  </div>
  @endif
</div>

@endsection
