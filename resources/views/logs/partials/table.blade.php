<table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
    <tr>
      <th class="px-4 py-3">Time</th>
      <th class="px-4 py-3">Env</th>
      <th class="px-4 py-3">Level</th>
      <th class="px-4 py-3">Source</th>
      <th class="px-4 py-3">Message</th>
    </tr>
  </thead>

  <tbody class="divide-y divide-gray-200">
    @forelse($entries as $entry)
    @php
    $entryLevel = strtolower($entry['level'] ?? 'info');
    $level = strtoupper($entryLevel);
    $color = match($entryLevel) {
    'error','critical' => 'text-red-600',
    'warning' => 'text-orange-600',
    default => 'text-gray-700'
    };
    @endphp

    <tr class="align-top odd:bg-white even:bg-gray-50 hover:bg-orange-50 transition-colors">
      <td class="py-3 px-4 whitespace-nowrap text-gray-700">{{ $entry['timestamp'] ?? '' }}</td>
      <td class="py-3 px-4 whitespace-nowrap text-gray-700">{{ $entry['environment'] ?? '' }}</td>
      <td class="py-3 px-4 whitespace-nowrap font-semibold {{ $color }}">{{ $level }}</td>
      <td class="py-3 px-4 whitespace-nowrap text-gray-600">{{ $entry['source'] ?? 'laravel.log' }}</td>
      <td class="py-3 px-4">
        <div class="font-medium text-gray-900">{{ $entry['message'] ?? '' }}</div>
        @if(!empty($entry['trace']))
        <details class="mt-2 text-xs text-gray-600">
          <summary class="cursor-pointer text-orange-600 hover:underline">Stacktrace anzeigen</summary>
          <pre class="mt-2 whitespace-pre-wrap bg-gray-50 p-3 rounded border border-gray-200 overflow-x-auto">{{ implode("\n", $entry['trace']) }}</pre>
        </details>
        @endif
      </td>
    </tr>
    @empty
    <tr>
      <td colspan="5" class="py-6 text-center text-gray-500">No log entries found.</td>
    </tr>
    @endforelse
  </tbody>
</table>
