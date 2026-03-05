@extends('layouts.app')

@section('content')

<div class="max-w-6xl mx-auto bg-white shadow-sm rounded p-6">

  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">System Logs</h1>
  </div>

  @if(empty($entries))
  <div class="text-gray-500 text-sm">
    Keine Logs gefunden.
  </div>
  @else

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm border">

      <thead class="bg-gray-100 text-left">
        <tr>
          <th class="px-4 py-2 border-b">Zeit</th>
          <th class="px-4 py-2 border-b">Level</th>
          <th class="px-4 py-2 border-b">Message</th>
        </tr>
      </thead>

      <tbody>

        @foreach($entries as $entry)

        <tr class="border-b">

          <td class="px-4 py-2 whitespace-nowrap text-gray-600">
            {{ $entry['time'] }}
          </td>

          <td class="px-4 py-2">

            @php
            $colors = [
            'error' => 'text-red-600',
            'warning' => 'text-yellow-600',
            'info' => 'text-blue-600',
            'debug' => 'text-gray-600'
            ];
            @endphp

            <span class="{{ $colors[strtolower($entry['level'])] ?? 'text-gray-700' }}">
              {{ strtoupper($entry['level']) }}
            </span>

          </td>

          <td class="px-4 py-2 font-mono break-all">
            {{ $entry['message'] }}
          </td>

        </tr>

        @endforeach

      </tbody>

    </table>
  </div>

  @endif

</div>

<div class="bg-white shadow-sm rounded p-6">
  <h1 class="text-2xl font-semibold mb-6">System Logs</h1>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="border-b text-left text-gray-600">
          <th class="py-2 pr-4">Timestamp</th>
          <th class="py-2 pr-4">Environment</th>
          <th class="py-2 pr-4">Level</th>
          <th class="py-2">Message</th>
        </tr>
      </thead>
      <tbody>
        @forelse($entries as $entry)
        <tr class="border-b align-top">
          <td class="py-2 pr-4 whitespace-nowrap">{{ $entry['timestamp'] }}</td>
          <td class="py-2 pr-4">{{ $entry['environment'] }}</td>
          <td class="py-2 pr-4">{{ $entry['level'] }}</td>
          <td class="py-2 break-all">{{ $entry['message'] }}</td>
        </tr>
        @empty
        <tr>
          <td colspan="4" class="py-4 text-gray-500">No log entries found.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
