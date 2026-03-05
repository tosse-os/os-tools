@extends('layouts.app')

@section('content')
<div class="space-y-6">
  <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 sm:p-8">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">System Logs</h1>
        <p class="text-sm text-gray-500 mt-1">Recent application log events with visual severity indicators.</p>
      </div>
      <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 w-fit">
        {{ count($entries) }} entries
      </span>
    </div>

    <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-100 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
          <tr>
            <th class="py-3 px-4">Timestamp</th>
            <th class="py-3 px-4">Environment</th>
            <th class="py-3 px-4">Level</th>
            <th class="py-3 px-4">Message</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          @forelse($entries as $entry)
          <tr class="align-top odd:bg-white even:bg-gray-50 hover:bg-orange-50 transition-colors">
            <td class="py-3 px-4 whitespace-nowrap text-gray-700">{{ $entry['timestamp'] }}</td>
            <td class="py-3 px-4 whitespace-nowrap text-gray-700">{{ $entry['environment'] }}</td>
            <td class="py-3 px-4">
              @php($level = strtoupper($entry['level']))
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                {{ $level === 'ERROR' ? 'bg-red-100 text-red-700 ring-1 ring-red-200' : '' }}
                {{ $level === 'WARNING' ? 'bg-yellow-100 text-yellow-700 ring-1 ring-yellow-200' : '' }}
                {{ $level === 'INFO' ? 'bg-blue-100 text-blue-700 ring-1 ring-blue-200' : '' }}
                {{ $level === 'DEBUG' ? 'bg-gray-200 text-gray-700 ring-1 ring-gray-300' : '' }}
                {{ !in_array($level, ['ERROR', 'WARNING', 'INFO', 'DEBUG']) ? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200' : '' }}">
                {{ $entry['level'] }}
              </span>
            </td>
            <td class="py-3 px-4">
              <pre class="font-mono text-xs leading-relaxed text-gray-800 whitespace-pre-wrap break-words">{{ $entry['message'] }}</pre>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="4" class="py-8 px-4 text-center text-gray-500">No log entries found.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection
