@extends('layouts.app')

@section('content')
<div class="space-y-6">
  <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 sm:p-8">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Live Logs</h1>
        <p class="text-sm text-gray-500 mt-1">Streaming view of incoming logs in a terminal-style console.</p>
      </div>
      <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 w-fit">
        Auto refresh: 2s
      </span>
    </div>

    <div class="mt-6 rounded-xl border border-emerald-900/40 bg-[#050b12] p-4 shadow-inner">
      <div id="live-log-console" class="h-[560px] overflow-y-auto rounded-lg bg-black/40 p-4 font-mono text-sm leading-relaxed text-emerald-300 whitespace-pre-wrap break-words"></div>
    </div>
  </section>
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
