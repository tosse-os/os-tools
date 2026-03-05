@extends('layouts.app')

@section('content')

<div class="bg-black text-green-400 p-4 rounded-lg font-mono text-sm h-[600px] overflow-auto" id="log-console"></div>

@endsection


@section('scripts')

<script>
  const consoleEl = document.getElementById('log-console')

  async function loadLogs() {

    const res = await fetch('/logs/raw')

    const logs = await res.json()

    consoleEl.innerHTML = ''

    logs.forEach(entry => {

      const line = document.createElement('div')

      line.textContent =
        `[${entry.timestamp}] ${entry.level.toUpperCase()} ${entry.message}`

      consoleEl.appendChild(line)

    })

    consoleEl.scrollTop = consoleEl.scrollHeight
  }

  setInterval(loadLogs, 2000)

  loadLogs()
</script>

@endsection
