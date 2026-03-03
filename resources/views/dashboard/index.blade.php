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
