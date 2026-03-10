@extends('layouts.app')

@section('content')
<form id="live-scan-form" class="mb-6 flex gap-2 items-center">
  <input type="url" name="url" required value="https://orange-services.de" placeholder="https://example.com"
    class="flex-1 border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-orange-500">
  <button type="submit"
    class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition flex items-center gap-2">
    <span>Scan starten</span>
    <span id="scan-spinner" class="hidden w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
  </button>
</form>

<div class="mb-4 flex flex-wrap items-center gap-4">
  <label><input type="checkbox" class="check-option" value="alt"> ALT-Check</label>
  <label><input type="checkbox" class="check-option" value="heading"> Überschriften</label>
  <label><input type="checkbox" class="check-option" value="status"> HTTP-Status</label>
</div>

<div id="abort-section" class="mb-4 hidden">
  <button id="abort-button" class="text-sm px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">
    Scan abbrechen
  </button>
</div>

<div id="scan-progress" class="mb-4 text-sm text-gray-700">
  <div class="flex justify-between items-center mb-2">
    <span id="progress-text">Noch kein Scan gestartet.</span>
    <span id="progress-count" class="text-xs text-gray-500">0 / 0</span>
  </div>
  <div class="w-full bg-gray-200 rounded-full h-3">
    <div id="progress-bar" class="bg-orange-500 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
  </div>
</div>

<div id="scan-details" class="mb-4 rounded border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
  <div><strong>Scan stage:</strong> <span id="scan-stage">queued</span></div>
  <div class="mt-1"><strong>Current page:</strong> <span id="current-url" class="break-all">-</span></div>
  <div class="mt-1"><strong>Pages scanned:</strong> <span id="pages-scanned">0</span></div>
  <div class="mt-1"><strong>Queue size:</strong> <span id="queue-size">0</span></div>
</div>

<div id="failed-alert" class="mb-4 hidden p-3 rounded bg-red-100 text-red-700 flex items-center justify-between">
  <span>⚠ Scan failed</span>
  <button id="retry-button" class="px-3 py-1 rounded bg-red-600 text-white hover:bg-red-700">Retry</button>
</div>

<div class="overflow-x-auto bg-white shadow-sm rounded">
  <table class="min-w-full text-sm border-collapse" id="result-table">
    <thead class="bg-gray-100 text-left">
      <tr>
        <th class="p-2 border-b">#</th>
        <th class="p-2 border-b">URL</th>
        <th class="p-2 border-b">Status</th>
        <th class="p-2 border-b">ALT</th>
        <th class="p-2 border-b">Headings</th>
        <th class="p-2 border-b">Fehler</th>
      </tr>
    </thead>
    <tbody id="result-body"></tbody>
  </table>
</div>
@endsection

@vite(['resources/js/liveScan.js'])
