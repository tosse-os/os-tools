document.addEventListener('DOMContentLoaded', () => {

  const form = document.getElementById('live-scan-form');
  const spinner = document.getElementById('scan-spinner');
  const progressEl = document.getElementById('progress-text');
  const tbody = document.getElementById('result-body');
  const abortBtn = document.getElementById('abort-button');
  const abortSection = document.getElementById('abort-section');

  let scanId = null;
  let currentIndex = 0;
  let pollingInterval = null;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const url = form.url.value;
    const checks = Array.from(document.querySelectorAll('.check-option:checked')).map(el => el.value);

    tbody.innerHTML = '';
    currentIndex = 0;
    progressEl.textContent = 'Scan gestartet...';
    spinner.classList.remove('hidden');
    abortSection.classList.remove('hidden');

    const res = await fetch("/scan", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      },
      body: JSON.stringify({ url, checks })
    });

    const data = await res.json();
    if (!data.scanId) return;

    scanId = data.scanId;
    startPolling();
  });

  function startPolling() {
    pollingInterval = setInterval(async () => {

      const progressRes = await fetch(`/scan/${scanId}/progress?ts=${Date.now()}`);
      const progress = await progressRes.json();

      progressEl.textContent = `Scanne Seite ${progress.current} / ${progress.total}`;

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

      if (progress.status === 'done' || progress.status === 'aborted') {
        clearInterval(pollingInterval);
        spinner.classList.add('hidden');
        abortSection.classList.add('hidden');
        progressEl.textContent = progress.status === 'aborted'
          ? 'Scan abgebrochen'
          : 'Scan abgeschlossen';
      }

    }, 1000);
  }

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
