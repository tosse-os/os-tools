@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto bg-white shadow-sm rounded p-6">

  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Letzte Reports</h1>
    <a href="{{ route('reports.archive') }}" class="text-sm text-blue-600 hover:underline">
      Gesamte Historie anzeigen
    </a>
  </div>

  @if($errors->has('reports'))
  <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
    {{ $errors->first('reports') }}
  </div>
  @endif

  <form action="{{ route('reports.compare') }}" method="GET" class="space-y-4">
    <div>
      <button
        type="submit"
        class="inline-flex items-center rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition">
        Compare Reports
      </button>
    </div>

    <div class="space-y-4">
      @forelse($reports as $report)
      <div class="block border rounded p-4 hover:bg-gray-50 transition">
        <div class="flex justify-between items-center gap-4">
          <div class="flex items-start gap-3">
            <input
              type="checkbox"
              name="reports[]"
              value="{{ $report->id }}"
              class="mt-1 h-4 w-4 rounded border-gray-300"
              @checked(collect(request('reports', old('reports', [])))->contains($report->id))>

            <a href="{{ route('reports.show', $report) }}" class="block">
              <div class="font-medium">{{ $report->url }}</div>
              <div class="text-xs text-gray-500">
                {{ $report->created_at->format('d.m.Y H:i') }}
              </div>
            </a>
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
      </div>
      @empty
      <div class="text-gray-500">Keine Reports vorhanden.</div>
      @endforelse
    </div>
  </form>

</div>

@endsection
