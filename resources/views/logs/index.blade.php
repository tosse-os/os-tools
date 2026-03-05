@extends('layouts.app')

@section('content')
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
