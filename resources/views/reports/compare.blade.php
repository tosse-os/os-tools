@extends('layouts.app')

@section('content')
@php
    $reportList = collect($reports ?? [])->take(4)->values();
    $reportCount = $reportList->count();

    $domain = '';
    if ($reportCount > 0) {
        $firstUrl = (string) ($reportList[0]->url ?? '');
        $domain = parse_url($firstUrl, PHP_URL_HOST) ?: $firstUrl;
    }

    $gridCols = [
        1 => 'grid-cols-1',
        2 => 'grid-cols-1 md:grid-cols-2',
        3 => 'grid-cols-1 md:grid-cols-3',
        4 => 'grid-cols-1 md:grid-cols-4',
    ][$reportCount] ?? 'grid-cols-1';

    $reportSummaries = $reportList->map(function ($report) use ($comparisonModules, $comparisonData) {
        $overallScore = 0;
        $overallMax = 0;

        foreach ($comparisonModules as $moduleName) {
            $module = $comparisonData[$moduleName][$report->id] ?? [];
            $overallScore += (int) ($module['score'] ?? 0);
            $overallMax += (int) ($module['max_score'] ?? 0);
        }

        $scoreValue = isset($report->score) ? (int) $report->score : $overallScore;
        $percent = $overallMax > 0
            ? (int) round(($overallScore / $overallMax) * 100)
            : max(0, min(100, $scoreValue));

        return [
            'id' => $report->id,
            'report' => $report,
            'score' => $scoreValue,
            'overall_score' => $overallScore,
            'overall_max' => $overallMax,
            'percent' => $percent,
            'started_at' => $report->started_at,
        ];
    })->values();
@endphp

<div class="max-w-7xl mx-auto bg-white shadow-sm rounded-lg p-8 space-y-6 border border-gray-100">
    <div class="flex justify-between items-center gap-3">
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

    <div class="rounded-lg border border-gray-200 p-6 bg-gray-50 space-y-5">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Scan History Comparison</h2>
            <p class="text-sm text-gray-600 mt-1">Domain: <span class="font-medium text-gray-800">{{ $domain ?: '—' }}</span></p>
            <p class="text-sm text-gray-600">Comparing {{ $reportCount }} {{ \\Illuminate\\Support\\Str::plural('scan', $reportCount) }}</p>
        </div>

        <div class="grid {{ $gridCols }} gap-4">
            @foreach($reportSummaries as $index => $summary)
                @php
                    $previous = $reportSummaries[$index + 1] ?? null;
                    $trend = '→';
                    $trendText = 'Unchanged';
                    $trendClass = 'text-gray-600';

                    if ($previous) {
                        if ($summary['score'] > $previous['score']) {
                            $trend = '↑';
                            $trendText = 'Improvement';
                            $trendClass = 'text-green-700';
                        } elseif ($summary['score'] < $previous['score']) {
                            $trend = '↓';
                            $trendText = 'Decline';
                            $trendClass = 'text-red-700';
                        }
                    }
                @endphp
                <div class="rounded-lg shadow border border-gray-200 p-4 bg-white space-y-3">
                    <p class="text-sm text-gray-600">Scan Date</p>
                    <p class="text-base font-semibold text-gray-900">
                        {{ $summary['started_at'] ? $summary['started_at']->format('d M Y') : '—' }}
                    </p>
                    <p class="text-sm text-gray-600">
                        {{ $summary['started_at'] ? $summary['started_at']->format('H:i') : '—' }}
                    </p>

                    <div>
                        <p class="text-sm text-gray-600">SEO Score</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $summary['score'] }} / 100</p>
                    </div>

                    <div class="w-full bg-gray-200 h-2 rounded overflow-hidden">
                        <div class="bg-green-500 h-2 rounded" style="width: {{ $summary['percent'] }}%"></div>
                    </div>

                    <p class="text-xs {{ $trendClass }}">{{ $trend }} {{ $trendText }}</p>
                </div>
            @endforeach
        </div>
    </div>

    @if($mode === 'delta')
        <div class="rounded-lg shadow border border-gray-200 p-6 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100 text-left">
                    <tr>
                        <th class="px-4 py-3 border-b">Module</th>
                        @foreach($reportSummaries as $summary)
                            <th class="px-4 py-3 border-b whitespace-nowrap">
                                {{ $summary['started_at'] ? $summary['started_at']->format('d M') : '—' }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($comparisonModules as $module)
                        @php
                            $scores = [];
                            foreach ($reportSummaries as $summary) {
                                $moduleData = $comparisonData[$module][$summary['id']] ?? [];
                                $scores[] = (int) ($moduleData['score'] ?? 0);
                            }

                            $bestScore = count($scores) ? max($scores) : 0;
                        @endphp
                        <tr>
                            <td class="px-4 py-3 border-b font-semibold align-top">{{ ucfirst($module) }}</td>
                            @foreach($scores as $scoreIndex => $score)
                                @php
                                    $prevScore = $scores[$scoreIndex + 1] ?? null;
                                    $deltaText = '→';
                                    $deltaClass = 'text-gray-500';

                                    if (!is_null($prevScore)) {
                                        if ($score > $prevScore) {
                                            $deltaText = '↑';
                                            $deltaClass = 'text-green-700';
                                        } elseif ($score < $prevScore) {
                                            $deltaText = '↓';
                                            $deltaClass = 'text-red-700';
                                        }
                                    }
                                @endphp
                                <td class="px-4 py-3 border-b align-top {{ $score === $bestScore ? 'bg-green-50 font-semibold' : '' }}">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $score }}</span>
                                        <span class="text-xs {{ $deltaClass }}">{{ $deltaText }}</span>
                                    </div>
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
                                <div class="text-xs text-gray-500">{{ $summary['score'] }}/100</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($comparisonModules as $module)
                        @php
                            $scores = [];
                            foreach ($reportSummaries as $summary) {
                                $moduleData = $comparisonData[$module][$summary['id']] ?? [];
                                $scores[] = [
                                    'score' => (int) ($moduleData['score'] ?? 0),
                                    'max' => (int) ($moduleData['max_score'] ?? 0),
                                ];
                            }

                            $bestScore = count($scores) ? max(array_column($scores, 'score')) : 0;
                        @endphp
                        <tr>
                            <td class="px-4 py-3 border-b font-semibold">{{ ucfirst($module) }}</td>
                            @foreach($scores as $scoreIndex => $scoreData)
                                @php
                                    $score = $scoreData['score'];
                                    $max = $scoreData['max'];
                                    $prevScore = $scores[$scoreIndex + 1]['score'] ?? null;
                                    $trend = '→';
                                    $trendClass = 'text-gray-500';

                                    if (!is_null($prevScore)) {
                                        if ($score > $prevScore) {
                                            $trend = '↑';
                                            $trendClass = 'text-green-700';
                                        } elseif ($score < $prevScore) {
                                            $trend = '↓';
                                            $trendClass = 'text-red-700';
                                        }
                                    }
                                @endphp
                                <td class="px-4 py-3 border-b align-top {{ $score === $bestScore ? 'bg-green-50 font-semibold' : '' }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <span>{{ $score }}{{ $max > 0 ? ' / ' . $max : '' }}</span>
                                        <span class="text-xs {{ $trendClass }}">{{ $trend }}</span>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
