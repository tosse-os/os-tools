@extends('layouts.app')

@section('content')
<div class="bg-white shadow-sm rounded p-6">
  <h1 class="text-2xl font-semibold mb-6">Live Logs</h1>

  <div id="live-log-console" class="bg-black text-green-400 font-mono text-sm p-4 rounded h-[600px] overflow-y-auto whitespace-pre-wrap"></div>
</div>
@endsection

@section('scripts')
<script>
  const liveLogConsole = document.getElementById('live-log-console');

  async function loadLogs() {
    try {
      const response = await fetch('/logs/raw');
      const payload = await response.json();
      const lines = payload.lines || [];

      liveLogConsole.textContent = lines.join('');
      liveLogConsole.scrollTop = liveLogConsole.scrollHeight;
    } catch (error) {
      liveLogConsole.textContent = 'Unable to load logs.';
    }
  }

  loadLogs();
  setInterval(loadLogs, 2000);
</script>
@endsection
