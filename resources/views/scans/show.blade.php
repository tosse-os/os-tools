@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
  <h1 class="text-2xl font-semibold mb-6">Scan-Details</h1>

  <div class="mb-6 text-sm text-gray-700">
    <div><strong>Start-URL:</strong> <a href="{{ $scan->url }}" class="text-orange-600 underline" target="_blank">{{ $scan->url }}</a></div>
    <div><strong>Status:</strong> <span class="font-medium">{{ $scan->status }}</span></div>
    <div><strong>Erstellt:</strong> {{ $scan->created_at->format('d.m.Y H:i') }}</div>
    <div><strong>Seiten gefunden:</strong> {{ $scan->results()->count() }}</div>
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
