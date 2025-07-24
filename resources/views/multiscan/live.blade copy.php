@extends('layouts.app')

@section('content')
<form id="live-scan-form" class="mb-6 flex gap-2 items-center">
  <input type="url" name="url" required placeholder="https://example.com" value="https://orange-services.de"
    class="flex-1 border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-orange-500">
  <button type="submit"
    class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition">
    Scan starten
  </button>
</form>

<div class="flex items-center gap-2 mb-4">
  <h1 class="text-xl font-bold">Live-Scan</h1>
  <svg id="scan-spinner" class="animate-spin h-5 w-5 text-orange-600 hidden" viewBox="0 0 24 24" fill="none">
    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
  </svg>
</div>

<div id="scan-progress" class="mb-4 text-sm text-gray-700">
  <span id="progress-text">Noch kein Scan gestartet.</span>
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
    <tbody class="text-sm text-gray-800" id="result-body">
    </tbody>
  </table>
</div>
@endsection

@section('scripts')
<script>
  const form = document.getElementById('live-scan-form');
  const spinner = document.getElementById('scan-spinner');
  const progressEl = document.getElementById('progress-text');
  const tbody = document.getElementById('result-body');

  let scanId = null;
  let currentIndex = 0;
  let pollingInterval = null;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const url = form.url.value;

    tbody.innerHTML = '';
    currentIndex = 0;
    progressEl.textContent = 'Scan gestartet...';
    spinner.classList.remove('hidden');

    const res = await fetch("{{ route('scan.start') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: JSON.stringify({
        url
      })
    });

    const data = await res.json();
    if (!data.scanId) return;

    scanId = data.scanId;
    startPolling();
  });

  function startPolling() {
    pollingInterval = setInterval(async () => {
      try {
        const progressRes = await fetch(`/scan/${scanId}/progress?ts=${Date.now()}`);
        const progress = await progressRes.json();

        if (!progress || typeof progress.total !== 'number') return;

        progressEl.textContent = `Scanne Seite ${progress.current} von ${progress.total}: ${progress.url ?? '...'}`;

        while (currentIndex < progress.current) {
          const res = await fetch(`/scan/${scanId}/result/${currentIndex}`);
          if (!res.ok) break;
          const row = await res.json();

          const altErrors = row.altCheck?.altMissing ?? '–';
          const headingCount = Array.isArray(row.headingCheck?.list) ? row.headingCheck.list.length : 0;
          const headingErrors = row.headingCheck?.errors?.join(', ') ?? '';
          const errorDisplay = row.error ?? headingErrors ?? '–';

          const tr = document.createElement('tr');
          tr.className = 'border-b hover:bg-gray-50';
          console.log('Scan-Row', row);

          tr.innerHTML = `
            <td class="p-2">${currentIndex + 1}</td>
            <td class="p-2 break-all"><a href="${row.url}" class="text-orange-600 hover:underline" target="_blank">${row.url}</a></td>
            <td class="p-2">${row.statusCheck?.status ?? '–'}</td>
            <td class="p-2">${altErrors} Fehler</td>
            <td class="p-2">${headingCount} Überschriften</td>
            <td class="p-2 text-red-600 font-medium">${errorDisplay}</td>
          `;
          tbody.appendChild(tr);
          currentIndex++;
        }

        if (progress.status === 'done' && currentIndex >= progress.total) {
          clearInterval(pollingInterval);
          spinner.classList.add('hidden');
          progressEl.textContent = '✅ Scan abgeschlossen';
        }
      } catch (err) {
        console.error('Polling-Fehler:', err);
      }
    }, 1000);
  }
</script>
@endsection
