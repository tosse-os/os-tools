@extends('layouts.app')

@section('content')

<div class="max-w-6xl mx-auto">

  <h1 class="text-2xl font-semibold mb-8">
    Dashboard
  </h1>

  <div class="grid grid-cols-2 gap-6 mb-10">

    @foreach(config('reports.types') as $type => $report)

    <a href="{{ route($report['route']) }}"
      class="bg-white p-6 rounded shadow-sm hover:shadow-md transition border border-gray-100">

      <h2 class="text-lg font-semibold mb-2">
        {{ $report['label'] }}
      </h2>

      <p class="text-sm text-gray-600">
        Neuen Report starten
      </p>

    </a>

    @endforeach

  </div>


  <div class="bg-white rounded shadow-sm p-6 mb-10">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">System Status</h2>
      <a href="{{ route('queues.index') }}" class="text-sm text-blue-600 hover:underline">Queue Monitor öffnen</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="rounded border border-gray-200 p-4">
        <div class="text-xs uppercase text-gray-500 mb-2">Queue Status</div>
        <div class="space-y-1 text-sm">
          <div><span class="font-semibold">Active Jobs:</span> {{ $activeJobs }}</div>
          <div><span class="font-semibold">Queued Jobs:</span> {{ $queuedJobs }}</div>
          <div><span class="font-semibold">Failed Jobs:</span> {{ $failedJobs }}</div>
        </div>
      </div>

      <div class="rounded border border-gray-200 p-4">
        <div class="text-xs uppercase text-gray-500 mb-2">Running Scans</div>
        <div class="text-2xl font-semibold text-yellow-700">{{ $runningScans }}</div>
      </div>

      <div class="rounded border border-gray-200 p-4">
        <div class="text-xs uppercase text-gray-500 mb-2">Failed Scans</div>
        <div class="text-2xl font-semibold text-red-700">{{ $failedScans }}</div>
      </div>
    </div>
  </div>

  <div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold">
      Letzte Reports
    </h2>

    <a href="{{ route('reports.index') }}"
      class="text-sm text-blue-600 hover:underline">
      Alle Reports anzeigen
    </a>
  </div>

  <div class="bg-white rounded shadow-sm divide-y">

    @forelse($latestReports as $report)

    <a href="{{ route('reports.show', $report) }}"
      class="flex justify-between items-center p-4 hover:bg-gray-50 transition">

      <div>
        <div class="text-sm font-medium">
          {{ strtoupper($report->type) }} – {{ $report->url }}
        </div>

        <div class="text-xs text-gray-500">
          {{ $report->created_at->format('d.m.Y H:i') }}
        </div>
      </div>

      <div class="flex items-center gap-6">

        <div class="text-sm font-semibold">
          {{ $report->score ?? '-' }}
        </div>

        @php
        $statusColors = [
        'queued' => 'bg-gray-100 text-gray-600',
        'running' => 'bg-yellow-100 text-yellow-700',
        'done' => 'bg-green-100 text-green-700',
        'aborted' => 'bg-red-100 text-red-700',
        ];
        @endphp

        <span class="px-2 py-1 text-xs rounded {{ $statusColors[$report->status] ?? 'bg-gray-100 text-gray-600' }}">
          {{ $report->status }}
        </span>

      </div>

    </a>

    @empty

    <div class="p-6 text-sm text-gray-500">
      Noch keine Reports vorhanden.
    </div>

    @endforelse

  </div>

</div>

@endsection
