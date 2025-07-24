@extends('layouts.app')

@section('content')

<div class="flex items-center gap-2 mb-4">
  <h1 class="text-xl font-bold">Live-Scan</h1>
  <svg id="scan-spinner" class="animate-spin h-5 w-5 text-orange-600" viewBox="0 0 24 24" fill="none">
    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
  </svg>
</div>
<div id="scan-progress" class="mb-4 text-sm text-gray-700">
  <span id="progress-text">Starte Scan...</span>
</div>
<button id="abort-button" class="mt-2 inline-flex items-center text-sm text-red-600 hover:underline transition">
  Scan abbrechen
</button>
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
    <tbody class="text-sm text-gray-800" id="result-body">
    </tbody>
  </table>
</div>
@endsection

@section('scripts')
<script>
  const scanId = '{{ $scanId }}';
  const progressEl = document.getElementById('progress-text');
  const tbody = document.getElementById('result-body');

  let currentIndex = 0;
  let pollingStarted = false;
  let scanDone = false;

  const polling = setInterval(async () => {
    try {
      const progressRes = await fetch(`/scan-results/${scanId}/progress?ts=${Date.now()}`);
      const progress = await progressRes.json();

      if (!progress || typeof progress.total !== 'number') return;

      const total = progress.total;
      const isDone = progress.status === 'done' || progress.status === 'aborted';
      const canStartPolling = progress.current > 0;

      progressEl.textContent = `Scanne Seite ${progress.current} von ${progress.total}: ${progress.url ?? '...'}`;

      if (!pollingStarted && canStartPolling) {
        pollingStarted = true;
      }

      if (!pollingStarted) return;

      if (currentIndex < progress.current) {
        const res = await fetch(`/scan-results/${scanId}/${currentIndex}`);
        if (!res.ok) return;

        const row = await res.json();
        if (!row || Object.keys(row).length === 0) return;

        const altErrors = row.altCheck?.altMissing ?? '–';
        const headingCount = Array.isArray(row.headingCheck?.list) ? row.headingCheck.list.length : 0;
        const headingErrors = row.headingCheck?.errors?.join(', ') ?? '';
        const errorDisplay = row.error ?? headingErrors ?? '–';

        const tr = document.createElement('tr');
        tr.className = 'border-b hover:bg-gray-50';
        tr.innerHTML = `
        <td class="p-2">${currentIndex + 1}</td>
        <td class="p-2 break-all"><a href="${row.url ?? '–#'}" class="text-orange-600 hover:underline" target="_blank">${row.url ?? '–'}</td>
        <td class="p-2">${row.statusCheck?.status ?? '–'}</td>
        <td class="p-2">${altErrors} Fehler</td>
        <td class="p-2">${headingCount} Überschriften</td>
        <td class="p-2 text-red-600 font-medium">${errorDisplay}</td>
      `;
        tbody.appendChild(tr);
        currentIndex++;
      }

      if (isDone && currentIndex >= total) {
        clearInterval(polling);
        document.getElementById('scan-spinner')?.remove();
        progressEl.textContent = 'Scan abgeschlossen';
      }
    } catch (err) {
      console.error('Polling-Fehler:', err);
    }
  }, 1000);
  document.getElementById('abort-button').addEventListener('click', async () => {
    const res = await fetch('/multiscan/abort', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: JSON.stringify({
        scanId
      })
    });

    if (res.ok) {
      progressEl.textContent = '⛔️ Scan wurde abgebrochen';
      document.getElementById('scan-spinner')?.remove();
      document.getElementById('abort-button').remove();
      clearInterval(polling);
    }
  });
</script>


@endsection
