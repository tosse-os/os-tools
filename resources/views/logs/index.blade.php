@extends('layouts.app')

@section('content')

<div class="bg-white shadow rounded-lg p-6">

  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-semibold text-gray-900">System Logs</h1>
      <p class="text-sm text-gray-500 mt-1">Recent application log events.</p>
    </div>

    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 w-fit">
      {{ count($entries) }} entries
    </span>
  </div>


  <div class="overflow-x-auto">

    <table class="min-w-full divide-y divide-gray-200 text-sm">

      <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3">Time</th>
          <th class="px-4 py-3">Env</th>
          <th class="px-4 py-3">Level</th>
          <th class="px-4 py-3">Message</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-gray-200">

        @forelse($entries as $entry)

        @php
        $level = strtoupper($entry['level'] ?? 'info');
        $color = match($entry['level'] ?? 'info') {
        'error','critical' => 'text-red-600',
        'warning' => 'text-orange-600',
        default => 'text-gray-700'
        };
        @endphp

        <tr class="align-top odd:bg-white even:bg-gray-50 hover:bg-orange-50 transition-colors">

          <td class="py-3 px-4 whitespace-nowrap text-gray-700">
            {{ $entry['timestamp'] ?? '' }}
          </td>

          <td class="py-3 px-4 whitespace-nowrap text-gray-700">
            {{ $entry['environment'] ?? '' }}
          </td>

          <td class="py-3 px-4 whitespace-nowrap font-semibold {{ $color }}">
            {{ $level }}
          </td>

          <td class="py-3 px-4">

            <div class="font-medium text-gray-900">
              {{ $entry['message'] ?? '' }}
            </div>

            @if(!empty($entry['trace']))

            <details class="mt-2 text-xs text-gray-600">

              <summary class="cursor-pointer text-orange-600 hover:underline">
                Stacktrace anzeigen
              </summary>

              <pre class="mt-2 whitespace-pre-wrap bg-gray-50 p-3 rounded border border-gray-200 overflow-x-auto">
              {{ implode("\n", $entry['trace']) }}
              </pre>

            </details>

            @endif

          </td>

        </tr>

        @empty

        <tr>
          <td colspan="4" class="py-6 text-center text-gray-500">
            No log entries found.
          </td>
        </tr>

        @endforelse

      </tbody>

    </table>

  </div>

</div>

@endsection
