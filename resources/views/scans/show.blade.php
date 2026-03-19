@extends('layouts.app')

@section('content')
<div
  class="max-w-5xl mx-auto px-4 py-6"
  x-data="scanMonitor('{{ $scan->id }}', '{{ $scan->url }}', '{{ $scan->status }}')"
  x-init="init()">
  <h1 class="text-2xl font-semibold mb-6">Scan-Details</h1>

  <div class="mb-6 text-sm text-gray-700">
    <div><strong>Start-URL:</strong> <a href="{{ $scan->url }}" class="text-orange-600 underline" target="_blank">{{ $scan->url }}</a></div>
    <div><strong>Status:</strong> <span class="font-medium" x-text="status"></span></div>
    <div><strong>Erstellt:</strong> {{ $scan->created_at->format('d.m.Y H:i') }}</div>
    <div><strong>Seiten gefunden:</strong> {{ $scan->results()->count() }}</div>
  </div>

  <div class="bg-white border border-gray-200 rounded p-4 mb-6">
    <div class="flex items-center justify-between mb-2">
      <h2 class="font-semibold">Scan Progress</h2>
      <span class="text-sm text-gray-600" x-text="`${current} / ${total} pages scanned`"></span>
    </div>

    <div class="w-full bg-gray-200 rounded-full h-3">
      <div class="bg-orange-500 h-3 rounded-full transition-all duration-300" :style="`width: ${progressPercent}%`"></div>
    </div>

    <p class="text-xs text-gray-500 mt-2" x-text="statusLabel"></p>

    <div x-show="status === 'failed'" class="mt-3 p-3 rounded bg-red-100 text-red-700 flex items-center justify-between">
      <span>⚠ Scan failed</span>
      <form action="{{ url('/crawler') }}" method="GET">
        <button type="submit" class="px-3 py-1 rounded bg-red-600 text-white hover:bg-red-700">
          Retry
        </button>
      </form>
    </div>
  </div>

  @if ($scan->results->isEmpty())
  <p class="text-gray-500">Für diesen Scan sind noch keine Ergebnisse vorhanden.</p>
  @else
  <div class="overflow-x-auto bg-white shadow rounded">
    <table class="min-w-full table-auto text-sm text-left border-collapse">
      <thead class="bg-gray-100 border-b">
        <tr>
          <th class="px-4 py-2">#</th>
          <th class="px-4 py-2">URL</th>
          <th class="px-4 py-2">Status</th>
          <th class="px-4 py-2">ALT</th>
          <th class="px-4 py-2">Headings</th>
          <th class="px-4 py-2">Fehler</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        @foreach ($scan->results as $result)
        @php $data = $result->payload; @endphp
        <tr>
          <td class="px-4 py-2">{{ $loop->iteration }}</td>
          <td class="px-4 py-2 break-all">
            <a href="{{ $data['url'] ?? '#' }}" class="text-orange-600 hover:underline" target="_blank">
              {{ $data['url'] ?? '–' }}
            </a>
          </td>
          <td class="px-4 py-2">{{ $data['statusCheck']['status'] ?? '–' }}</td>
          <td class="px-4 py-2">{{ $data['altCheck']['altMissing'] ?? 0 }} Fehler</td>
          <td class="px-4 py-2">
            {{ count($data['headingCheck']['list'] ?? []) }} Überschriften
          </td>
          <td class="px-4 py-2 text-red-600">
            {{ implode(', ', $data['headingCheck']['errors'] ?? []) ?: '–' }}
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>
@endsection

@section('scripts')
<script>
  function scanMonitor(scanId, scanUrl, initialStatus) {
    return {
      scanId,
      scanUrl,
      status: initialStatus || 'queued',
      current: 0,
      total: 0,
      intervalRef: null,
      get progressPercent() {
        if (!this.total) {
          return 0;
        }

        return Math.min(100, Math.round((this.current / this.total) * 100));
      },
      get statusLabel() {
        return `Status: ${this.status}`;
      },
      init() {
        this.fetchProgress();
        this.intervalRef = setInterval(() => this.fetchProgress(), 3000);
      },
      async fetchProgress() {
        const response = await fetch(`/crawls/${this.scanId}/progress?ts=${Date.now()}`);
        const data = await response.json();

        this.status = data.status ?? this.status;
        this.current = data.current ?? this.current;
        this.total = data.total ?? this.total;

        if (['done', 'aborted', 'failed'].includes(this.status) && this.intervalRef) {
          clearInterval(this.intervalRef);
          this.intervalRef = null;
        }
      }
    }
  }
</script>
@endsection
