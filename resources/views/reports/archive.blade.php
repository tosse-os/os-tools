@extends('layouts.app')

@section('content')

<div class="max-w-6xl mx-auto bg-white shadow-sm rounded-lg border border-gray-100 p-8 space-y-6">

  <div class="flex justify-between items-center">
    <h1 class="text-2xl font-semibold">Report Archiv</h1>
    <a href="{{ route('dashboard') }}" class="text-sm text-blue-600 hover:underline">
      Zurück zum Dashboard
    </a>
  </div>

  @if($errors->has('reports'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ $errors->first('reports') }}
    </div>
  @endif

  <form method="GET" action="{{ route('reports.archive') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
    <div>
      <label for="keyword" class="block text-xs font-medium text-gray-700 mb-1">Keyword</label>
      <input id="keyword" type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" class="w-full rounded-lg border-gray-300 text-sm" placeholder="z. B. Glaserei">
    </div>
    <div>
      <label for="city" class="block text-xs font-medium text-gray-700 mb-1">City</label>
      <input id="city" type="text" name="city" value="{{ $filters['city'] ?? '' }}" class="w-full rounded-lg border-gray-300 text-sm" placeholder="z. B. München">
    </div>
    <div>
      <label for="domain" class="block text-xs font-medium text-gray-700 mb-1">Domain</label>
      <input id="domain" type="text" name="domain" value="{{ $filters['domain'] ?? '' }}" class="w-full rounded-lg border-gray-300 text-sm" placeholder="z. B. example.de">
    </div>
    <div class="flex gap-2">
      <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition">Filtern</button>
      <a href="{{ route('reports.archive') }}" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 transition">Reset</a>
    </div>
  </form>

  @if($reportContexts->isEmpty())
    <div class="text-gray-500 text-sm">
      Noch keine Reports vorhanden.
    </div>
  @else

    <form action="{{ route('reports.compare') }}" method="GET" class="space-y-4">
      <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition">
        Compare Reports
      </button>

      @foreach($reportContexts as $context)
        <details class="rounded-lg border shadow" @if($loop->first) open @endif>
          <summary class="list-none cursor-pointer p-4 bg-gray-50 hover:bg-gray-100">
            <div class="flex justify-between gap-4">
              <div>
                <div class="font-semibold text-gray-900">{{ $context['keyword'] }} • {{ $context['city'] }}</div>
                <div class="text-sm text-gray-700">Domain: {{ $context['domain'] }}</div>
                <div class="text-xs text-gray-500">{{ $context['reports_count'] }} reports</div>
              </div>
              <div class="text-right text-sm font-semibold text-gray-800">
                Last score: {{ is_numeric($context['last_score']) ? number_format($context['last_score'], 0) : '—' }}
              </div>
            </div>
          </summary>

          <div class="overflow-x-auto border-t">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-100 text-left">
                <tr>
                  <th class="px-4 py-3 border-b">Vergleich</th>
                  <th class="px-4 py-3 border-b">Date</th>
                  <th class="px-4 py-3 border-b">Keyword</th>
                  <th class="px-4 py-3 border-b">City</th>
                  <th class="px-4 py-3 border-b">Domain</th>
                  <th class="px-4 py-3 border-b">Score</th>
                  <th class="px-4 py-3 border-b">Status</th>
                  <th class="px-4 py-3 border-b text-right">Aktion</th>
                </tr>
              </thead>
              <tbody>
                @foreach($context['reports'] as $report)
                  @php
                    $keyword = $report->keyword ?: '—';
                    $city = $report->city ?: '—';
                    $domain = parse_url((string) ($report->url ?? ''), PHP_URL_HOST) ?: '—';
                    $startedAt = $report->started_at && strtotime((string) $report->started_at) !== false
                      ? \Carbon\Carbon::parse($report->started_at)
                      : null;
                    $scoreValue = is_numeric($report->score) ? (float) $report->score : null;
                    $scorePercent = $scoreValue !== null ? (int) max(0, min(100, round($scoreValue))) : 0;
                  @endphp
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 border-b align-top">
                      <input
                        type="checkbox"
                        name="reports[]"
                        value="{{ $report->id }}"
                        class="h-4 w-4 rounded border-gray-300"
                        @checked(collect(request('reports', old('reports', [])))->contains($report->id))>
                    </td>
                    <td class="px-4 py-3 border-b whitespace-nowrap">{{ $startedAt ? $startedAt->format('d.m.Y H:i') : '—' }}</td>
                    <td class="px-4 py-3 border-b">{{ $keyword }}</td>
                    <td class="px-4 py-3 border-b">{{ $city }}</td>
                    <td class="px-4 py-3 border-b"><div class="truncate max-w-xs">{{ $domain }}</div></td>
                    <td class="px-4 py-3 border-b min-w-[170px]">
                      <div class="space-y-2">
                        <div class="font-semibold">{{ $scoreValue !== null ? number_format($scoreValue, 2) : '—' }}</div>
                        <div class="w-28 bg-gray-200 h-2 rounded overflow-hidden">
                          <div class="bg-green-500 h-2 rounded" style="width: {{ $scorePercent }}%"></div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-3 border-b">
                      @if($report->status === 'done')
                        <span class="text-green-600 font-semibold">Done</span>
                      @elseif($report->status === 'running')
                        <span class="text-yellow-600 font-semibold">Running</span>
                      @elseif($report->status === 'queued')
                        <span class="text-gray-600 font-semibold">Queued</span>
                      @else
                        <span class="text-red-600 font-semibold">Error</span>
                      @endif
                    </td>
                    <td class="px-4 py-3 border-b text-right">
                      <a href="{{ route('reports.show', $report->id) }}" class="text-blue-600 hover:underline">Anzeigen</a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </details>
      @endforeach
    </form>

  @endif

</div>

@endsection
