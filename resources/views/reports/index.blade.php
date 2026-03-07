@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto bg-white shadow-sm rounded-lg border border-gray-100 p-6 space-y-6">

  <div class="flex justify-between items-center">
    <h1 class="text-2xl font-semibold">Letzte Reports</h1>
    <div class="flex items-center gap-4">
      <form method="GET" action="{{ route('localseo.form') }}" id="startAnalysisForm">
        <button
          type="submit"
          id="startAnalysisButton"
          class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition disabled:cursor-not-allowed disabled:opacity-70">
          Analyse starten
        </button>
      </form>

      <a href="{{ route('reports.archive') }}" class="text-sm text-blue-600 hover:underline">
        Gesamte Historie anzeigen
      </a>
    </div>
  </div>

  @if($errors->has('reports'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ $errors->first('reports') }}
    </div>
  @endif

  <form method="GET" action="{{ route('reports.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
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
      <a href="{{ route('reports.index') }}" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 transition">Reset</a>
    </div>
  </form>

  <form action="{{ route('reports.compare') }}" method="GET" class="space-y-4">
    <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition">
      Compare Reports
    </button>

    @forelse($reportContexts as $context)
      <details class="border rounded-lg shadow-sm" @if($loop->first) open @endif>
        <summary class="list-none cursor-pointer p-4 hover:bg-gray-50">
          <div class="flex justify-between items-start gap-4">
            <div>
              <div class="font-semibold text-gray-900">{{ $context['keyword'] }} • {{ $context['city'] }}</div>
              <div class="text-sm text-gray-700">Domain: {{ $context['domain'] }}</div>
              <div class="text-xs text-gray-500">{{ $context['reports_count'] }} reports</div>
            </div>
            <div class="text-right text-sm text-gray-700">
              <div class="font-semibold">Last score: {{ is_numeric($context['last_score']) ? number_format($context['last_score'], 0) : '—' }}</div>
            </div>
          </div>
        </summary>

        <div class="px-4 pb-4 space-y-3 border-t bg-gray-50/40">
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
            <div class="block border rounded-lg shadow-sm p-4 bg-white">
              <div class="flex justify-between items-start gap-4">
                <div class="flex items-start gap-3">
                  <input
                    type="checkbox"
                    name="reports[]"
                    value="{{ $report->id }}"
                    class="mt-1 h-4 w-4 rounded border-gray-300"
                    @checked(collect(request('reports', old('reports', [])))->contains($report->id))>

                  <a href="{{ route('reports.show', $report) }}" class="block space-y-1 text-sm">
                    <div class="font-semibold text-gray-900">{{ $keyword }} • {{ $city }}</div>
                    <div class="text-gray-700">{{ $domain }}</div>
                    <div class="text-xs text-gray-500">{{ $startedAt ? $startedAt->format('d.m.Y H:i') : '—' }}</div>
                  </a>
                </div>

                <div class="text-right text-sm space-y-2 min-w-[160px]">
                  <div class="font-semibold text-gray-900">Score {{ $scoreValue !== null ? number_format($scoreValue, 0) : '—' }}</div>
                  <div class="w-28 ml-auto bg-gray-200 h-2 rounded overflow-hidden">
                    <div class="bg-green-500 h-2 rounded" style="width: {{ $scorePercent }}%"></div>
                  </div>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </details>
    @empty
      <div class="text-gray-500">Keine Reports vorhanden.</div>
    @endforelse
  </form>

</div>

@endsection

@section('scripts')
<script>
  const startAnalysisForm = document.getElementById('startAnalysisForm');
  const startAnalysisButton = document.getElementById('startAnalysisButton');

  if (startAnalysisForm && startAnalysisButton) {
    startAnalysisForm.addEventListener('submit', () => {
      startAnalysisButton.disabled = true;
      startAnalysisButton.textContent = 'Analyse wird gestartet...';
    });
  }
</script>
@endsection
