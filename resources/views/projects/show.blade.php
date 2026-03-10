@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
  <nav class="text-sm text-gray-500">
    <a href="{{ route('projects.index') }}" class="hover:text-gray-700">Projects</a>
    <span class="mx-2">/</span>
    <span class="text-gray-700">{{ $project->name }}</span>
  </nav>

  <header>
    <h1 class="text-2xl font-semibold">{{ $project->name }}</h1>
    <p class="text-gray-600 mt-1">{{ $project->domain }}</p>
  </header>

  <section class="space-y-4">
    <h2 class="text-lg font-semibold">Analyses</h2>

    @forelse($project->analyses as $analysis)
      @php
        $latestReport = $analysis->reports->first();
        $crawlerReports = $analysis->reports->where('type', 'crawler')->count();
        $localSeoReports = $analysis->reports->where('type', 'local_seo')->count();
        $seoReports = $analysis->reports->where('type', 'seo')->count();
      @endphp

      <a
        href="{{ route('analyses.show', $analysis) }}"
        class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md transition">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-lg font-semibold text-gray-900">{{ $analysis->keyword ?: '—' }} • {{ $analysis->city ?: '—' }}</div>
            <div class="mt-2 text-sm text-gray-700">Score {{ is_numeric($latestReport?->score) ? number_format((float) $latestReport->score, 0) : '—' }}</div>
            <div class="text-sm text-gray-600">{{ $analysis->reports_count }} Reports</div>
            <div class="mt-2 text-xs text-gray-500">Crawler Reports: {{ $crawlerReports }} • Local SEO Reports: {{ $localSeoReports }} • SEO Reports: {{ $seoReports }}</div>
          </div>
        </div>
      </a>
    @empty
      <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500">
        Keine Analysen vorhanden.
      </div>
    @endforelse
  </section>
</div>
@endsection
