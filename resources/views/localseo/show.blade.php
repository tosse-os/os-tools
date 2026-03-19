@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
  <nav class="text-sm text-gray-500">
    <a href="{{ route('projects.index') }}" class="hover:text-gray-700">Projects</a>
    <span class="mx-2">/</span>
    <a href="{{ route('projects.show', $localseo->project) }}" class="hover:text-gray-700">{{ $localseo->project->name }}</a>
    <span class="mx-2">/</span>
    <span class="text-gray-700">{{ $localseo->keyword ?: '—' }} {{ $localseo->city ?: '—' }}</span>
  </nav>

  <header>
    <h1 class="text-2xl font-semibold">{{ $localseo->keyword ?: '—' }} • {{ $localseo->city ?: '—' }}</h1>
    <p class="text-gray-700 mt-1">{{ $localseo->project->domain }}</p>
    <p class="text-sm text-gray-500 mt-1">Project: {{ $localseo->project->name }}</p>
  </header>

  <section class="space-y-4">
    <h2 class="text-lg font-semibold">Result History</h2>

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
          @forelse($localseo->reports as $result)
            <tr class="hover:bg-gray-50 transition">
              <td class="px-4 py-3">
                <a href="{{ route('results.show', $result) }}" class="text-blue-600 hover:underline">
                  {{ $result->started_at?->format('d.m.Y H:i') ?? '—' }}
                </a>
              </td>
              <td class="px-4 py-3 text-gray-800">{{ is_numeric($result->score) ? number_format((float) $result->score, 0) : '—' }}</td>
              <td class="px-4 py-3 text-gray-700">{{ $result->status ?? '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="px-4 py-6 text-center text-gray-500">Keine Results vorhanden.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection
