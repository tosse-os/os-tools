@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto bg-white shadow-sm rounded-lg border border-gray-100 p-6">

  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Letzte Reports</h1>
    <a href="{{ route('reports.archive') }}" class="text-sm text-blue-600 hover:underline">
      Gesamte Historie anzeigen
    </a>
  </div>

  @if($errors->has('reports'))
  <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
    {{ $errors->first('reports') }}
  </div>
  @endif

  <form action="{{ route('reports.compare') }}" method="GET" class="space-y-4">
    <div>
      <button
        type="submit"
        class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition">
        Compare Reports
      </button>
    </div>

    <div class="space-y-4">
      @forelse($reports as $report)
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
      <div class="block border rounded-lg shadow p-4 hover:bg-gray-50 transition">
        <div class="flex justify-between items-start gap-4">
          <div class="flex items-start gap-3">
            <input
              type="checkbox"
              name="reports[]"
              value="{{ $report->id }}"
              class="mt-1 h-4 w-4 rounded border-gray-300"
              @checked(collect(request('reports', old('reports', [])))->contains($report->id))>

            <a href="{{ route('reports.show', $report) }}" class="block space-y-2 text-sm">
              <div class="font-semibold text-gray-900">{{ $keyword }} • {{ $city }}</div>
              <div class="text-gray-700">{{ $domain }}</div>
              <div class="text-xs text-gray-500">{{ $startedAt ? $startedAt->format('d.m.Y H:i') : '—' }}</div>
            </a>
          </div>

          <div class="text-right text-sm space-y-2 min-w-[160px]">
            <div class="font-semibold text-gray-900">Score {{ $scoreValue !== null ? number_format($scoreValue, 2) : '—' }}</div>
            <div class="text-xs text-gray-600">
              Score: {{ $scoreValue !== null ? number_format($scoreValue, 0) : '0' }} / 100
            </div>
            <div class="w-28 ml-auto bg-gray-200 h-2 rounded overflow-hidden">
              <div class="bg-green-500 h-2 rounded" style="width: {{ $scorePercent }}%"></div>
            </div>
            <div class="text-xs text-gray-500">{{ $report->status }}</div>
          </div>
        </div>
      </div>
      @empty
      <div class="text-gray-500">Keine Reports vorhanden.</div>
      @endforelse
    </div>
  </form>

</div>

@endsection
