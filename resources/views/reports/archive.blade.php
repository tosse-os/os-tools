@extends('layouts.app')

@section('content')

<div class="max-w-6xl mx-auto bg-white shadow-sm rounded-lg border border-gray-100 p-8 space-y-8">

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

  @if($reports->isEmpty())
  <div class="text-gray-500 text-sm">
    Noch keine Reports vorhanden.
  </div>
  @else

  <form action="{{ route('reports.compare') }}" method="GET" class="space-y-4">
    <div>
      <button
        type="submit"
        class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition">
        Compare Reports
      </button>
    </div>

    <div class="overflow-x-auto rounded-lg border shadow">
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
          @foreach($reports as $report)
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

            <td class="px-4 py-3 border-b whitespace-nowrap">
              {{ $startedAt ? $startedAt->format('d.m.Y H:i') : '—' }}
            </td>

            <td class="px-4 py-3 border-b">
              {{ $keyword }}
            </td>

            <td class="px-4 py-3 border-b">
              {{ $city }}
            </td>

            <td class="px-4 py-3 border-b">
              <div class="truncate max-w-xs">{{ $domain }}</div>
            </td>

            <td class="px-4 py-3 border-b min-w-[170px]">
              <div class="space-y-2">
                <div class="font-semibold">
                  {{ $scoreValue !== null ? number_format($scoreValue, 2) : '—' }}
                </div>
                <div class="text-xs text-gray-600">
                  Score: {{ $scoreValue !== null ? number_format($scoreValue, 0) : '0' }} / 100
                </div>
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
              <a
                href="{{ route('reports.show', $report->id) }}"
                class="text-blue-600 hover:underline">
                Anzeigen
              </a>
            </td>
          </tr>

          @endforeach
        </tbody>

      </table>
    </div>
  </form>

  @endif

</div>

@endsection
