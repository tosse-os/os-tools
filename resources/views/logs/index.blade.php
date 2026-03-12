@extends('layouts.app')

@section('content')
<div class="bg-white shadow rounded-lg p-6">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-semibold text-gray-900">System Logs</h1>
      <p class="text-sm text-gray-500 mt-1">Recent application log events.</p>
    </div>

    <span id="entry-count" class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 w-fit">
      {{ count($entries) }} entries
    </span>
  </div>

  <div class="mb-4 max-w-xs">
    <label for="log-level-filter" class="block text-sm font-medium text-gray-700 mb-1">Log Level</label>
    <select id="log-level-filter" class="w-full rounded-lg border-gray-300 focus:border-orange-400 focus:ring-orange-400">
      <option value="all" @selected($level === 'all')>All</option>
      @foreach ($allowedLevels as $logLevel)
      <option value="{{ $logLevel }}" @selected($level === $logLevel)>{{ ucfirst($logLevel) }}</option>
      @endforeach
    </select>
  </div>

  <div id="logs-table-container" class="overflow-x-auto">
    @include('logs.partials.table', ['entries' => $entries])
  </div>
</div>
@endsection

@section('scripts')
<script>
  const levelSelect = document.getElementById('log-level-filter')
  const tableContainer = document.getElementById('logs-table-container')
  const entryCount = document.getElementById('entry-count')

  async function reloadLogs(level) {
    const url = new URL(window.location.href)
    url.searchParams.set('level', level)

    const response = await fetch(url.toString(), {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })

    if (!response.ok) {
      return
    }

    const data = await response.json()
    tableContainer.innerHTML = data.html
    entryCount.textContent = `${data.count} entries`
    window.history.replaceState({}, '', url)
  }

  levelSelect?.addEventListener('change', (event) => {
    reloadLogs(event.target.value)
  })
</script>
@endsection
