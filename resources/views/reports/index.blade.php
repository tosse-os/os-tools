@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto bg-white shadow-sm rounded p-6">

  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Letzte Reports</h1>
    <a href="{{ route('reports.archive') }}" class="text-sm text-blue-600 hover:underline">
      Gesamte Historie anzeigen
    </a>
  </div>

  <div class="space-y-4">
    @forelse($reports as $report)
    <a href="{{ route('reports.show', $report) }}"
      class="block border rounded p-4 hover:bg-gray-50 transition">

      <div class="flex justify-between items-center">
        <div>
          <div class="font-medium">{{ $report->url }}</div>
          <div class="text-xs text-gray-500">
            {{ $report->created_at->format('d.m.Y H:i') }}
          </div>
        </div>

        <div class="text-right">
          <div class="text-sm font-semibold">
            {{ $report->score ?? '-' }}
          </div>
          <div class="text-xs text-gray-500">
            {{ $report->status }}
          </div>
        </div>
      </div>

    </a>
    @empty
    <div class="text-gray-500">Keine Reports vorhanden.</div>
    @endforelse
  </div>

</div>

@endsection
