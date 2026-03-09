@extends('layouts.app')

@section('content')

<div class="space-y-4">
  <div class="flex justify-between items-center">
    <h1 class="text-lg font-semibold text-gray-900">Live Logs</h1>

    <form id="clear-logs-form" method="POST" action="{{ route('logs.clear') }}">
      @csrf
      <button
        type="submit"
        class="px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm"
      >
        Clear Logs
      </button>
    </form>
  </div>

  <div class="bg-black text-green-400 p-4 rounded-lg font-mono text-sm h-[600px] overflow-auto" id="log-console"></div>
</div>

@endsection


@section('scripts')

<script>
  const consoleEl = document.getElementById('log-console')
  const clearForm = document.getElementById('clear-logs-form')

  function isAtBottom(el) {
    const threshold = 16

    return el.scrollTop + el.clientHeight >= el.scrollHeight - threshold
  }

  async function loadLogs() {
    const shouldStickToBottom = isAtBottom(consoleEl)

    const res = await fetch('/logs/raw')
    const logs = await res.json()

    const previousScrollTop = consoleEl.scrollTop

    consoleEl.innerHTML = ''

    logs.forEach(entry => {
      const line = document.createElement('div')
      line.textContent = `[${entry.timestamp}] ${entry.level.toUpperCase()} ${entry.message}`
      consoleEl.appendChild(line)
    })

    if (shouldStickToBottom) {
      consoleEl.scrollTop = consoleEl.scrollHeight
    } else {
      consoleEl.scrollTop = previousScrollTop
    }
  }

  clearForm.addEventListener('submit', async (event) => {
    event.preventDefault()

    await fetch(clearForm.action, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        'Accept': 'application/json'
      }
    })

    await loadLogs()
  })

  setInterval(loadLogs, 2000)

  loadLogs()
</script>

@endsection
