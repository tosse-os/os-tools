document.addEventListener('DOMContentLoaded', () => {

  const form = document.getElementById('live-scan-form');
  const spinner = document.getElementById('scan-spinner');
  const progressEl = document.getElementById('progress-text');
  const tbody = document.getElementById('result-body');
  const abortBtn = document.getElementById('abort-button');
  const abortSection = document.getElementById('abort-section');
  const progressBar = document.getElementById('progress-bar');
  const progressCount = document.getElementById('progress-count');
  const failedAlert = document.getElementById('failed-alert');
  const retryBtn = document.getElementById('retry-button');
  const scanStageEl = document.getElementById('scan-stage');
  const currentUrlEl = document.getElementById('current-url');
  const pagesScannedEl = document.getElementById('pages-scanned');
  const queueSizeEl = document.getElementById('queue-size');

  let scanId = null;
  let currentIndex = 0;
  let pollingInterval = null;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const url = form.url.value;
    const checks = Array.from(document.querySelectorAll('.check-option:checked')).map(el => el.value);

    console.log('[SCAN TRACE] button_clicked', {
      url,
      checks,
    });

    tbody.innerHTML = '';
    currentIndex = 0;
    progressEl.textContent = 'Scanning site...';
    progressCount.textContent = '0 / 0';
    progressBar.style.width = '0%';
    scanStageEl.textContent = 'initializing';
    currentUrlEl.textContent = '-';
    pagesScannedEl.textContent = '0';
    queueSizeEl.textContent = '0';
    failedAlert.classList.add('hidden');
    spinner.classList.remove('hidden');
    abortSection.classList.remove('hidden');

    console.log('[SCAN TRACE] sending_scan_request');

    const res = await fetch("/scan", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      },
      body: JSON.stringify({ url, checks })
    });

    const data = await res.json();
    console.log('[SCAN TRACE] scan_request_response', data);
    if (!data.scanId) return;

    scanId = data.scanId;
    startPolling();
  });

  function startPolling() {
    if (pollingInterval) {
      clearInterval(pollingInterval);
    }

    pollingInterval = setInterval(async () => {

      const progressRes = await fetch(`/scans/${scanId}/progress?ts=${Date.now()}`);
      const progress = await progressRes.json();

      progressEl.textContent = 'Scanning site...';
      progressCount.textContent = `${progress.current} / ${progress.total}`;
      scanStageEl.textContent = progress.stage ?? progress.status ?? 'running';
      currentUrlEl.textContent = progress.current_url ?? '-';
      pagesScannedEl.textContent = `${progress.scanned_pages ?? progress.current} / ${progress.total}`;
      queueSizeEl.textContent = `${progress.queue_size ?? 0}`;

      const percent = progress.total > 0
        ? Math.min(100, Math.round((progress.current / progress.total) * 100))
        : 0;
      progressBar.style.width = `${percent}%`;

      while (currentIndex < progress.current) {

        const res = await fetch(`/scan/${scanId}/result/${currentIndex}`);
        const row = await res.json();

        const tr = document.createElement('tr');
        tr.className = 'border-b hover:bg-gray-50';

        tr.innerHTML = `
          <td class="p-2">${currentIndex + 1}</td>
          <td class="p-2 break-all"><a href="${row.url}" class="text-orange-600 hover:underline" target="_blank">${row.url}</a></td>
          <td class="p-2">${row.statusCheck?.status ?? '–'}</td>
          <td class="p-2">${row.altCheck?.altMissing ?? 0}</td>
          <td class="p-2">${row.headingCheck?.list?.length ?? 0}</td>
          <td class="p-2 text-red-600">${row.error ?? (row.headingCheck?.errors?.join(', ') ?? '–')}</td>
        `;

        tbody.appendChild(tr);
        currentIndex++;
      }

      if (progress.status === 'done' || progress.status === 'aborted' || progress.status === 'failed') {
        clearInterval(pollingInterval);
        spinner.classList.add('hidden');
        abortSection.classList.add('hidden');

        if (progress.status === 'failed') {
          progressEl.textContent = '⚠ Scan failed';
          failedAlert.classList.remove('hidden');
        } else {
          progressEl.textContent = progress.status === 'aborted'
            ? 'Scan abgebrochen'
            : 'Scan abgeschlossen';
        }
      }

    }, 1000);
  }

  retryBtn.addEventListener('click', () => {
    form.requestSubmit();
  });

  abortBtn.addEventListener('click', async () => {

    if (!scanId) return;

    await fetch('/multiscan/abort', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      },
      body: JSON.stringify({ scanId })
    });

    progressEl.textContent = 'Abbruch wird verarbeitet...';
  });

});
