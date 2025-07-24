@extends('layouts.app')

@section('content')
<h1 class="text-xl font-bold mb-4">Scan #{{ $scan->id }}</h1>

<p><strong>URL:</strong> {{ $scan->url }}</p>
<p><strong>Status:</strong> {{ $scan->status }}</p>
<p><strong>Datum:</strong> {{ $scan->created_at->format('d.m.Y H:i') }}</p>

<div class="mb-6 flex gap-2 items-center mt-6">
  <span class="text-sm text-gray-600">Ansicht:</span>
  <a href="{{ request()->fullUrlWithQuery(['view' => 'card']) }}"
    class="px-3 py-1 rounded text-sm {{ request('view', 'card') === 'card' ? 'bg-orange-600 text-white' : 'bg-gray-200 text-gray-800' }}">
    Karten
  </a>
  <a href="{{ request()->fullUrlWithQuery(['view' => 'table']) }}"
    class="px-3 py-1 rounded text-sm {{ request('view') === 'table' ? 'bg-orange-600 text-white' : 'bg-gray-200 text-gray-800' }}">
    Tabelle
  </a>
</div>

@if (request('view') === 'table')

@php
$errorCount = collect($scan->result)->filter(fn($r) => isset($r['altCheck']) && is_array($r['altCheck']) && count($r['altCheck']) > 0)->count();
@endphp

<div class="mb-4 text-sm text-gray-700">
  <strong>Fehlerübersicht:</strong>
  {{ $errorCount }} von {{ count($scan->result) }} Seiten mit fehlenden ALT-Texten
</div>

<div class="mb-4">
  <a href="{{ route('scans.export.csv', $scan) }}"
    class="inline-block bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 text-sm">
    CSV-Export
  </a>
</div>

<div class="overflow-x-auto bg-white rounded shadow border border-gray-200">
  <table class="min-w-full divide-y divide-gray-200 text-sm text-left">
    <thead class="bg-gray-50 text-xs uppercase text-gray-500 tracking-wider">
      <tr>
        <th class="px-4 py-2">URL</th>
        <th class="px-4 py-2">Status</th>
        <th class="px-4 py-2">ALT</th>
        <th class="px-4 py-2">Headings</th>
        <th class="px-4 py-2">Fehler</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @foreach ($scan->result as $entry)
      <tr class="hover:bg-gray-50">
        <td class="px-4 py-2">{{ $entry['url'] ?? '–' }}</td>
        <td class="px-4 py-2">
          @php
          $status = $entry['statusCheck']['status'] ?? null;
          @endphp
          <div class="flex items-center gap-2">
            @if ($status >= 200 && $status < 400)
              <span class="inline-block w-3 h-3 rounded-full bg-green-500" title="OK"></span>
              @elseif ($status >= 400)
              <span class="inline-block w-3 h-3 rounded-full bg-red-500" title="Fehler"></span>
              @else
              <span class="inline-block w-3 h-3 rounded-full bg-gray-400" title="Unbekannt"></span>
              @endif
              <span>{{ $status ?? '–' }}</span>
          </div>
        </td>
        <td class="px-4 py-2">
          @php $alt = $entry['altCheck'] ?? null; @endphp
          @if (is_array($alt) && count($alt) > 0)
          <span class="text-red-600 font-semibold">{{ count($alt) }} Fehler</span>
          @elseif (is_array($alt))
          <span class="text-green-600 font-medium">OK</span>
          @else
          <span>–</span>
          @endif
        </td>
        <td class="px-4 py-2">
          @php $headings = $entry['headingCheck']['headings'] ?? null; @endphp
          {{ is_array($headings) ? count($headings) . ' gefunden' : '–' }}
        </td>
        <td class="px-4 py-2 text-red-600">
          {{ $entry['error'] ?? '–' }}
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

@else

@foreach ($scan->result as $entry)
<div class="mb-6 border rounded p-4 bg-white shadow-sm">
  <div class="flex justify-between items-start">
    <div>
      <p class="text-sm text-gray-500">{{ $entry['url'] }}</p>
      <p class="text-base font-semibold text-gray-800">{{ $entry['title'] ?? '(Kein Titel)' }}</p>
    </div>
    @if(isset($entry['error']))
    <span class="text-red-600 font-medium">Fehler: {{ $entry['error'] }}</span>
    @endif
  </div>

  <ul class="mt-3 text-sm text-gray-700 space-y-1">
    @foreach ($entry as $key => $value)
    @if (in_array($key, ['url', 'title', 'error'])) @continue @endif
    <li>
      <strong>{{ ucfirst($key) }}:</strong>
      @if (is_array($value))
      <span>{{ count($value) }} Eintrag(e)</span>
      @else
      <span>{{ $value }}</span>
      @endif
    </li>
    @endforeach
  </ul>
</div>
@endforeach

@endif
@endsection
