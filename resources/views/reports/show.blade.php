@extends('layouts.app')

@section('content')

@php
$result = $report->results->first();
$data = $result?->payload ?? [];
$modules = config('seo_modules');
$project = data_get($report, 'analysis.project.name') ?: '—';
$keyword = $report->keyword ?: '—';
$city = $report->city ?: '—';
$domain = parse_url($report->url, PHP_URL_HOST) ?: $report->url;
$createdAt = $report->created_at?->format('d.m.Y H:i') ?? '—';
$normalizedStatus = match ($report->status) {
  'queued' => 'queued',
  'processing', 'running' => 'processing',
  'done', 'completed' => 'done',
  'failed' => 'failed',
  default => 'queued',
};
$statusMeta = [
  'queued' => ['label' => 'In Warteschlange', 'icon' => '⏳', 'class' => 'text-yellow-700 bg-yellow-50 border-yellow-200'],
  'processing' => ['label' => 'Analyse läuft', 'icon' => '⚙', 'class' => 'text-blue-700 bg-blue-50 border-blue-200'],
  'done' => ['label' => 'Fertig', 'icon' => '✓', 'class' => 'text-green-700 bg-green-50 border-green-200'],
  'failed' => ['label' => 'Fehler', 'icon' => '⚠', 'class' => 'text-red-700 bg-red-50 border-red-200'],
];
$currentStatus = $statusMeta[$normalizedStatus];
$showLoadingState = in_array($normalizedStatus, ['queued', 'processing'], true);
$isCrawlerReport = $report->type === 'crawler';
$pagesCrawled = (int) ($data['pages_crawled'] ?? 0);
$crawlerPages = is_array($data['link_graph_pages'] ?? null) ? $data['link_graph_pages'] : [];
$internalLinks = is_array($data['internal_links'] ?? null) ? $data['internal_links'] : [];
$httpStatusCodes = is_array($data['http_status_codes'] ?? null) ? $data['http_status_codes'] : [];
@endphp

<div class="max-w-5xl mx-auto bg-white shadow-sm rounded p-8 space-y-10">

  <div class="space-y-4">
    <a href="{{ url('/reports') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 transition">
      ← Zurück zu Reports
    </a>

    <h1 class="text-2xl font-semibold">{{ $isCrawlerReport ? 'Crawler Analyse' : 'Local SEO Analyse' }}</h1>

    <div class="space-y-1">
      <div class="text-sm text-gray-600"><strong>Project:</strong> {{ $project }}</div>
      @if(!$isCrawlerReport)
      <div class="text-2xl font-semibold">{{ $keyword }} • {{ $city }}</div>
      @endif
      <div class="text-lg text-gray-700">{{ $domain }}</div>
      <div class="text-sm text-gray-600 pt-2">{{ $createdAt }}</div>
      <div class="text-sm"><strong>Score:</strong> {{ $report->score }}</div>
      @if(!$isCrawlerReport)
      <div class="text-sm"><strong>Rating:</strong> {{ $data['rating']['label'] ?? '-' }}</div>
      @endif
      <div class="pt-3">
        <div class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium {{ $currentStatus['class'] }}">
          <span>{{ $currentStatus['icon'] }}</span>
          <span>Status: {{ $currentStatus['label'] }}</span>
          @if($showLoadingState)
          <span class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
          @endif
        </div>
      </div>

      @if($showLoadingState)
      <div class="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800" id="reportLoadingState">
        <div class="flex items-center gap-3">
          <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-blue-300 border-t-blue-700"></span>
          <span>Die Analyse wird gerade durchgeführt.</span>
        </div>

        @if($normalizedStatus === 'queued')
        <div class="mt-2 text-xs text-blue-700">Die Analyse befindet sich in der Warteschlange.</div>
        @endif
      </div>
      @endif
    </div>
  </div>

  @if($regression || !empty($moduleRegressions))
  <div class="space-y-3 rounded-lg border border-red-200 bg-red-50 p-5">
    <h2 class="text-xl font-semibold text-red-900">Regressionen</h2>

    @if($regression)
    <div class="text-sm text-red-800">
      ⚠ SEO Regression erkannt<br>
      Score ist um {{ number_format($regression['drop'], 0) }} Punkte gefallen
    </div>
    @endif

    @foreach($moduleRegressions as $moduleRegression)
    <div class="text-sm text-red-800">⚠ {{ $moduleRegression['label'] }}</div>
    @endforeach
  </div>
  @endif

  <div>
    <h2 class="text-xl font-semibold mb-4">SEO Score Verlauf</h2>

    @if(count($timeline['data'] ?? []) < 2)
    <div class="text-sm text-gray-600">Noch nicht genug Daten für einen Verlauf.</div>
    @else
    <canvas id="seoTimeline"></canvas>
    @endif
  </div>

  @if($isCrawlerReport)
  <div>
    <h2 class="text-xl font-semibold mb-4">Crawler Ergebnisse</h2>
    <div class="grid gap-3 sm:grid-cols-3 mb-4 text-sm">
      <div class="rounded border p-3"><strong>Pages crawled:</strong> {{ $pagesCrawled }}</div>
      <div class="rounded border p-3"><strong>Discovered pages:</strong> {{ count($crawlerPages) }}</div>
      <div class="rounded border p-3"><strong>Internal links:</strong> {{ count($internalLinks) }}</div>
    </div>

    <div class="overflow-x-auto rounded border">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left">URL</th>
            <th class="px-3 py-2 text-left">Page depth</th>
            <th class="px-3 py-2 text-left">Internal links</th>
            <th class="px-3 py-2 text-left">HTTP status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($crawlerPages as $crawlerPage)
          @php
          $pageUrl = $crawlerPage['url'] ?? '—';
          $statusCode = $httpStatusCodes[$pageUrl] ?? ($pageUrl === $report->url ? ($data['statusCheck']['status'] ?? null) : null);
          @endphp
          <tr class="border-t">
            <td class="px-3 py-2 break-all">{{ $pageUrl }}</td>
            <td class="px-3 py-2">{{ $crawlerPage['depth'] ?? '—' }}</td>
            <td class="px-3 py-2">{{ $crawlerPage['outgoing_links'] ?? 0 }}</td>
            <td class="px-3 py-2">{{ $statusCode ?? 'n/a' }}</td>
          </tr>
          @empty
          <tr class="border-t"><td colspan="4" class="px-3 py-4 text-gray-600">Keine Crawler-Daten verfügbar.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @endif

  <div>
    <h2 class="text-xl font-semibold mb-4">SEO Issues</h2>

    <div class="space-y-2 text-sm text-gray-700">
      <div><strong>Total:</strong> {{ $issuesSummary['issues_total'] ?? 0 }}</div>
      <div><strong>Critical:</strong> {{ $issuesSummary['critical_count'] ?? 0 }}</div>
      <div><strong>Warning:</strong> {{ $issuesSummary['warning_count'] ?? 0 }}</div>

      @if(($issueTypeSummary['missing_h1'] ?? 0) > 0)
      <div>Missing H1 on {{ $issueTypeSummary['missing_h1'] }} pages</div>
      @endif

      @if(($issueTypeSummary['thin_content'] ?? 0) > 0)
      <div>Thin content on {{ $issueTypeSummary['thin_content'] }} pages</div>
      @endif

      @if(($issueTypeSummary['missing_alt'] ?? 0) > 0)
      <div>Missing alt text on {{ $issueTypeSummary['missing_alt'] }} pages</div>
      @endif

      @if(empty($issueTypeSummary))
      <div class="text-gray-600">No SEO issues detected.</div>
      @endif
    </div>
  </div>

  <div>
    <h2 class="text-xl font-semibold mb-4">Top SEO Issues</h2>

    <div class="space-y-3">
      @forelse($insights as $insight)
      <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-yellow-900">
        <div class="font-semibold">
          ⚠ {{ $insight['title'] }}
        </div>
        <div class="text-sm mt-1">Score {{ number_format($insight['score'], 0) }}</div>
        @if($insight['recommendation'])
        <div class="text-sm mt-1">{{ $insight['recommendation'] }}</div>
        @endif
      </div>
      @empty
      <div class="text-sm text-gray-600">Keine kritischen SEO Issues gefunden.</div>
      @endforelse
    </div>
  </div>

  @foreach(($modules['dimensions'] ?? []) as $dimensionKey => $dimension)

  <div>
    <h2 class="text-xl font-semibold mb-2">
      {{ $dimension['label'] ?? '' }}
    </h2>

    <p class="text-sm text-gray-600 mb-6">
      {{ $dimension['description'] ?? '' }}
    </p>

    <div class="space-y-6">

      @foreach(($data['breakdown'] ?? []) as $key => $item)

      @php
      $config = $modules[$key] ?? null;
      @endphp

      @if($config && ($config['dimension'] ?? null) === $dimensionKey)

      @php
      $percentage = ($item['max'] ?? 0) > 0
      ? round((($item['score'] ?? 0) / $item['max']) * 100)
      : 0;

      $barColor = $percentage >= 80
      ? 'bg-green-500'
      : ($percentage >= 50 ? 'bg-yellow-500' : 'bg-red-500');
      @endphp

      <div x-data="{ open: false, explainOpen: false }" class="border rounded overflow-hidden">

        <button
          type="button"
          @click="open = !open"
          class="w-full flex justify-between items-center p-5 text-left hover:bg-gray-50 transition">

          <div>
            <div class="font-semibold text-lg">
              {{ $config['label'] ?? '' }}
            </div>
            <div class="text-sm text-gray-600">
              {{ $config['description'] ?? '' }}
            </div>
          </div>

          <div class="flex items-center gap-5">
            <div class="text-sm font-semibold">
              {{ $item['score'] ?? 0 }} / {{ $item['max'] ?? 0 }}
            </div>

            <span
              class="text-xl transition-transform duration-200"
              :class="{ 'rotate-180': open }">
              ▼
            </span>
          </div>
        </button>

        <div class="px-5 pb-4">
          <div class="w-full bg-gray-200 rounded h-2">
            <div class="h-2 rounded {{ $barColor }}"
              style="width: {{ $percentage }}%">
            </div>
          </div>
        </div>

        <div
          x-show="open"
          x-transition
          class="px-5 pb-6 space-y-6 text-sm border-t bg-gray-50">

          <div>
            <button type="button" class="font-medium mb-2 flex items-center gap-2" @click="explainOpen = !explainOpen">
              Erklärung
              <span :class="{ 'rotate-180': explainOpen }" class="inline-block transition-transform">▼</span>
            </button>
            <div x-show="explainOpen" x-transition class="rounded border border-blue-100 bg-blue-50 p-3 text-gray-700">
              {{ $config['description'] ?? '' }}<br>
              <span class="font-medium">Empfohlen:</span> {{ $config['how_to_fix'] ?? '' }}
            </div>
          </div>

          <div>
            <div class="font-medium mb-2">So wird bewertet:</div>

            <ul class="space-y-2">

              @foreach(($config['how_scoring_works'] ?? []) as $rule => $text)

              @php
              $value = $item['checks'][$rule] ?? null;
              @endphp

              <li class="flex justify-between items-center">

                <span>{{ $text }}</span>

                @if($value === true)
                <span class="text-green-600 font-semibold">✔</span>
                @elseif($value === false)
                <span class="text-red-600 font-semibold">✘</span>
                @else
                <span class="text-gray-400">–</span>
                @endif

              </li>

              @endforeach

            </ul>
          </div>

          @if(!empty($item['missing']))
          <div class="bg-red-50 border-l-4 border-red-500 p-4">
            <div class="font-medium mb-2">Verbesserung notwendig:</div>
            <ul class="list-disc ml-5">
              @foreach($item['missing'] as $missing)
              <li>{{ $missing }}</li>
              @endforeach
            </ul>
          </div>
          @endif

          <div class="bg-blue-50 border-l-4 border-blue-500 p-4">
            <div class="font-medium">Was ist zu tun:</div>
            <div>{{ $config['how_to_fix'] ?? '' }}</div>
          </div>

        </div>

      </div>

      @endif

      @endforeach

    </div>
  </div>

  @endforeach

</div>

@endsection

@section('scripts')
<script>
  (() => {
    const reportId = @json($report->id);
    let pollTimer = null;

    const normalizeStatus = (status) => {
      if (status === 'processing' || status === 'running') return 'processing';
      if (status === 'done' || status === 'completed') return 'done';
      if (status === 'failed') return 'failed';
      return 'queued';
    };

    const pollStatus = async () => {
      try {
        const response = await fetch(`/reports/${reportId}/status`, {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
          cache: 'no-store',
        });

        if (!response.ok) {
          return;
        }

        const data = await response.json();
        const normalized = normalizeStatus(data.status);

        if (normalized === 'done') {
          if (pollTimer) {
            clearInterval(pollTimer);
          }
          window.location.reload();
        }
      } catch (error) {
        // Silent fail to avoid user disruption while polling.
      }
    };

    const initialStatus = @json($normalizedStatus);
    if (initialStatus === 'queued' || initialStatus === 'processing') {
      pollTimer = setInterval(pollStatus, 3000);
      pollStatus();
    }
  })();
</script>
@if(count($timeline['data'] ?? []) >= 2)
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const timelineCtx = document.getElementById('seoTimeline');

  if (timelineCtx) {
    new Chart(timelineCtx, {
      type: 'line',
      data: {
        labels: @json($timeline['labels'] ?? []),
        datasets: [{
          label: 'SEO Score',
          data: @json($timeline['data'] ?? []),
          borderColor: '#16a34a',
          backgroundColor: 'transparent',
          tension: 0.35,
          fill: false,
          pointRadius: 3,
          pointHoverRadius: 5,
        }],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          x: {
            title: {
              display: true,
              text: 'Scan Date',
            },
          },
          y: {
            title: {
              display: true,
              text: 'Score',
            },
            beginAtZero: true,
          },
        },
      },
    });
  }
</script>
@endif
@endsection
