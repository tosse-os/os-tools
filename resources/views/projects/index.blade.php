@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
  <nav class="text-sm text-gray-500">
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Dashboard</a>
    <span class="mx-2">/</span>
    <span class="text-gray-700">Projects</span>
  </nav>

  <div>
    <h1 class="text-2xl font-semibold">Projects</h1>
    <p class="text-sm text-gray-600 mt-1">Übersicht aller Projekte.</p>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
    @forelse($projects as $project)
      @php
        $latestResult = $project->analyses
          ->flatMap(fn($localseo) => $localseo->reports)
          ->pluck('started_at')
          ->filter()
          ->max();
      @endphp

      <a
        href="{{ route('projects.show', $project) }}"
        class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md transition">
        <h2 class="text-lg font-semibold text-gray-900">{{ $project->name }}</h2>
        <p class="text-sm text-gray-600 mt-1">{{ $project->domain }}</p>

        <div class="mt-4 space-y-1 text-sm text-gray-700">
          <div>{{ $project->analyses_count }} Analyses</div>
          <div>Last Result: {{ $latestResult ? \Carbon\Carbon::parse($latestResult)->format('d M Y') : '—' }}</div>
        </div>
      </a>
    @empty
      <div class="col-span-full rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500">
        Keine Projekte vorhanden.
      </div>
    @endforelse
  </div>
</div>
@endsection
