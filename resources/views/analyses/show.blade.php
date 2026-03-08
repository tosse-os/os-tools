@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
  <nav class="text-sm text-gray-500">
    <a href="{{ route('projects.index') }}" class="hover:text-gray-700">Projects</a>
    <span class="mx-2">/</span>
    <a href="{{ route('projects.show', $analysis->project) }}" class="hover:text-gray-700">{{ $analysis->project->name }}</a>
    <span class="mx-2">/</span>
    <span class="text-gray-700">{{ $analysis->keyword ?: '—' }} {{ $analysis->city ?: '—' }}</span>
  </nav>

  <header>
    <h1 class="text-2xl font-semibold">{{ $analysis->keyword ?: '—' }} • {{ $analysis->city ?: '—' }}</h1>
    <p class="text-gray-700 mt-1">{{ $analysis->project->domain }}</p>
    <p class="text-sm text-gray-500 mt-1">Project: {{ $analysis->project->name }}</p>
  </header>

  <section class="space-y-4">
    <h2 class="text-lg font-semibold">Scan History</h2>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-semibold text-gray-700">Date</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-700">Score</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($analysis->reports as $report)
            <tr class="hover:bg-gray-50 transition">
              <td class="px-4 py-3">
                <a href="{{ route('reports.show', $report) }}" class="text-blue-600 hover:underline">
                  {{ $report->started_at?->format('d.m.Y H:i') ?? '—' }}
                </a>
              </td>
              <td class="px-4 py-3 text-gray-800">{{ is_numeric($report->score) ? number_format((float) $report->score, 0) : '—' }}</td>
              <td class="px-4 py-3 text-gray-700">{{ $report->status ?? '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="px-4 py-6 text-center text-gray-500">Keine Reports vorhanden.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection
