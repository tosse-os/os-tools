<?php

namespace App\Jobs;

use App\Models\Crawl;
use App\Models\CrawlLink;
use App\Models\CrawlPage;
use App\Models\Report;
use App\Models\Scan;
use App\Services\IssueDetectionService;
use App\Services\ReportPersistenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class RunScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $scanId;
    public array $checks;
    public bool $multiScan = false;

    public function __construct(string $scanId, array $checks = [], bool $multiScan = false)
    {
        $this->scanId = $scanId;
        $this->checks = $checks;
        $this->multiScan = $multiScan;
    }

    public function handle(ReportPersistenceService $reportPersistenceService): void
    {
        Log::debug('[SCAN TRACE] job_started', [
            'scan_id' => $this->scanId,
            'checks' => $this->checks,
        ]);

        Log::info('RunScan gestartet', [
            'scan_id' => $this->scanId,
            'checks' => $this->checks,
        ]);

        $report = Report::find($this->scanId);
        $scan = $report ? null : Scan::find($this->scanId);

        Log::debug('[SCAN TRACE] model_lookup', [
            'scan_id' => $this->scanId,
            'report_found' => (bool) $report,
            'scan_found' => (bool) $scan,
        ]);

        if ($report) {
            Log::debug('[SCAN TRACE] executing_report_scan', [
                'scan_id' => $this->scanId,
            ]);
            $this->runCrawlerReportScan($report, $reportPersistenceService);
        } elseif ($scan) {
            Log::debug('[SCAN TRACE] executing_multi_scan', [
                'scan_id' => $this->scanId,
            ]);
            $this->runMultiScan($scan, $reportPersistenceService);
        } else {
            Log::critical('[SCAN TRACE] lookup_failed', [
                'scan_id' => $this->scanId,
            ]);

            throw new RuntimeException('RunScan could not find Report or Scan for ID: '.$this->scanId);
        }

        Log::debug('[SCAN TRACE] job_completed', [
            'scan_id' => $this->scanId,
        ]);
    }

    private function runCrawlerReportScan(Report $report, ReportPersistenceService $reportPersistenceService): void
    {
        Log::info('[SCAN] Job gestartet', [
            'scan_id' => $report->id,
            'url' => $report->url,
        ]);

        $report->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        Crawl::whereKey($report->id)->update([
            'status' => 'running',
        ]);

        $options = [
            'scan_id' => $report->id,
            'url' => $report->url,
            'checks' => array_values(array_unique(array_merge($this->checks, ['status']))),
            'max_pages' => config('seo.max_pages', 20),
            'max_depth' => config('seo.max_depth', 2),
            'page_timeout' => config('seo.page_timeout', 30),
            'max_retries' => min((int) config('seo.max_retries', 2), 2),
            'retry_delay' => config('seo.retry_delay', 10),
            'max_scan_time' => config('seo.max_scan_time', 300),
            'concurrency' => (int) env('CRAWLER_CONCURRENCY', 6),
        ];

        $command = sprintf(
            'node %s %s',
            escapeshellarg(base_path('node-scanner/core/scanner.js')),
            escapeshellarg(json_encode($options, JSON_UNESCAPED_SLASHES))
        );

        $process = Process::fromShellCommandline($command, base_path());

        $process->setTimeout(null);
        Log::debug('[SCAN TRACE] node_process_start', [
            'scan_id' => $report->id,
            'target' => 'scanner.js',
        ]);
        Log::info('[SCAN] Starting Node Scanner', ['scan_id' => $report->id]);
        Log::debug('[SCAN TRACE] node_command', [
            'scan_id' => $report->id,
            'command' => $process->getCommandLine(),
        ]);
        Log::debug('[SCAN] Node command', ['command' => $process->getCommandLine()]);
        $stdoutBuffer = '';
        $scanResult = null;

        try {
            $process->run(function (string $type, string $buffer) use ($report, &$stdoutBuffer, &$scanResult): void {
                if ($type === Process::ERR) {
                    Log::error('[NODE STDERR]', [
                        'scan_id' => $report->id,
                        'output' => trim($buffer),
                    ]);

                    return;
                }

                $stdoutBuffer .= $buffer;
                $lines = preg_split('/\r?\n/', $stdoutBuffer) ?: [];
                $stdoutBuffer = array_pop($lines) ?? '';

                foreach ($lines as $line) {
                    if ($line === '') {
                        continue;
                    }

                    $payload = json_decode($line, true);
                    if (!is_array($payload)) {
                        Log::debug('[NODE STDOUT]', [
                            'scan_id' => $report->id,
                            'output' => trim($line),
                        ]);
                        continue;
                    }

                    if (($payload['type'] ?? null) === 'scan_result' && is_array($payload['result'] ?? null)) {
                        $scanResult = $payload['result'];
                        continue;
                    }

                    $this->persistScanEvent($report->id, $payload);
                }
            });
        } catch (\Throwable $e) {
            Log::error('[SCAN TRACE] node_process_exception', [
                'scan_id' => $report->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
        Log::debug('[SCAN TRACE] node_process_finished', [
            'scan_id' => $report->id,
            'exit_code' => $process->getExitCode(),
            'exit_code_text' => $process->getExitCodeText(),
            'successful' => $process->isSuccessful(),
        ]);
        Log::info('[SCAN] Node Scanner finished', ['scan_id' => $report->id]);

        if (!$process->isSuccessful()) {
            Log::error('Crawler Report Scan fehlgeschlagen', [
                'report_id' => $report->id,
                'error' => $process->getErrorOutput(),
            ]);

            $report->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);

            Crawl::whereKey($report->id)->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);

            return;
        }

        $firstResult = is_array($scanResult) ? $scanResult : null;

        if (!$firstResult) {
            Log::error('Crawler Report Scan lieferte kein Ergebnis', ['report_id' => $report->id]);
            $report->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);
            Crawl::whereKey($report->id)->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);
            return;
        }

        $directory = "scans/{$report->id}";
        Storage::makeDirectory($directory);
        Storage::put("{$directory}/0.json", json_encode($firstResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $pagesCrawled = (int) ($firstResult['pages_crawled'] ?? 1);
        if ($pagesCrawled < 1) {
            $pagesCrawled = 1;
        }

        Storage::put("{$directory}/progress.json", json_encode([
            'current' => $pagesCrawled,
            'total' => $pagesCrawled,
            'status' => 'done',
        ]));

        CrawlPage::where('crawl_id', $report->id)->delete();
        CrawlLink::where('crawl_id', $report->id)->delete();
        $this->resetCrawlIssues($report->id);

        $crawlPages = is_array($firstResult['link_graph_pages'] ?? null) ? $firstResult['link_graph_pages'] : [];
        $storedPagesByUrl = [];
        foreach ($crawlPages as $crawlPage) {
            if (!is_array($crawlPage) || empty($crawlPage['url'])) {
                continue;
            }

            $storedPage = CrawlPage::create([
                'crawl_id' => $report->id,
                'url' => $crawlPage['url'],
                'status' => isset($crawlPage['status']) ? (string) $crawlPage['status'] : null,
                'status_code' => isset($crawlPage['status_code'])
                    ? (int) $crawlPage['status_code']
                    : (isset($crawlPage['status']) && is_numeric($crawlPage['status']) ? (int) $crawlPage['status'] : null),
                'title' => $crawlPage['title'] ?? null,
                'canonical_url' => $crawlPage['canonical_url'] ?? null,
                'canonical' => $crawlPage['canonical'] ?? $crawlPage['canonical_url'] ?? null,
                'meta_description' => $crawlPage['meta_description'] ?? null,
                'alt_count' => (int) ($crawlPage['alt_count'] ?? $crawlPage['alt_missing_count'] ?? 0),
                'alt_missing_count' => (int) ($crawlPage['alt_missing_count'] ?? $crawlPage['alt_count'] ?? 0),
                'h1_count' => (int) ($crawlPage['h1_count'] ?? 0),
                'heading_count' => (int) ($crawlPage['heading_count'] ?? 0),
                'image_count' => (int) ($crawlPage['image_count'] ?? 0),
                'error' => $crawlPage['error'] ?? null,
                'content_hash' => $crawlPage['content_hash'] ?? $crawlPage['text_hash'] ?? null,
                'text_hash' => $crawlPage['text_hash'] ?? $crawlPage['content_hash'] ?? null,
                'internal_links' => (int) ($crawlPage['internal_links'] ?? $crawlPage['internal_links_count'] ?? 0),
                'external_links' => (int) ($crawlPage['external_links'] ?? $crawlPage['external_links_count'] ?? 0),
                'internal_links_in' => (int) ($crawlPage['internal_links_in'] ?? 0),
                'internal_links_out' => (int) ($crawlPage['internal_links_out'] ?? $crawlPage['outgoing_links'] ?? 0),
                'depth' => (int) ($crawlPage['depth'] ?? 0),
                'created_at' => now(),
            ]);

            $storedPagesByUrl[$storedPage->url] = $storedPage;
        }

        $crawlLinks = is_array($firstResult['crawl_links'] ?? null) ? $firstResult['crawl_links'] : [];
        $baseHost = parse_url((string) $report->url, PHP_URL_HOST);
        foreach ($crawlLinks as $crawlLink) {
            if (!is_array($crawlLink) || empty($crawlLink['source_url']) || empty($crawlLink['target_url'])) {
                continue;
            }

            $sourceUrl = (string) $crawlLink['source_url'];
            $targetUrl = (string) $crawlLink['target_url'];
            $targetHost = parse_url($targetUrl, PHP_URL_HOST);

            $isInternal = !empty($baseHost)
                && !empty($targetHost)
                && Str::lower((string) $targetHost) === Str::lower((string) $baseHost);

            $sourcePageId = $storedPagesByUrl[$sourceUrl]->id ?? null;

            CrawlLink::create([
                'crawl_id' => $report->id,
                'source_url' => $sourceUrl,
                'target_url' => $targetUrl,
                'link_type' => $isInternal ? 'internal' : 'external',
                'anchor_text' => $crawlLink['anchor_text'] ?? null,
                'nofollow' => (bool) ($crawlLink['nofollow'] ?? false),
                'status_code' => isset($crawlLink['status_code']) ? (int) $crawlLink['status_code'] : null,
                'redirect_target' => $crawlLink['redirect_target'] ?? null,
                'redirect_chain_length' => (int) ($crawlLink['redirect_chain_length'] ?? 0),
                'redirect_chain' => is_array($crawlLink['redirect_chain'] ?? null) ? $crawlLink['redirect_chain'] : null,
                'created_at' => now(),
            ]);

            $this->storeCrawlLinkSourcePageId($report->id, $sourceUrl, $targetUrl, $sourcePageId);
        }

        $this->persistCrawlIssues($report->id);

        Crawl::whereKey($report->id)->update([
            'pages_scanned' => $pagesCrawled,
            'pages_total' => $pagesCrawled,
            'status' => 'done',
            'finished_at' => now(),
        ]);

        $reportPersistenceService->syncFromStorage($report->fresh());
    }

    private function resetCrawlIssues(string $crawlId): void
    {
        if (!Schema::hasTable('crawl_issues')) {
            return;
        }

        DB::table('crawl_issues')->where('crawl_id', $crawlId)->delete();
    }

    private function storeCrawlLinkSourcePageId(string $crawlId, string $sourceUrl, string $targetUrl, ?int $sourcePageId): void
    {
        if ($sourcePageId === null || !Schema::hasTable('crawl_links') || !Schema::hasColumn('crawl_links', 'source_page_id')) {
            return;
        }

        DB::table('crawl_links')
            ->where('crawl_id', $crawlId)
            ->where('source_url', $sourceUrl)
            ->where('target_url', $targetUrl)
            ->whereNull('source_page_id')
            ->limit(1)
            ->update(['source_page_id' => $sourcePageId]);
    }

    private function persistCrawlIssues(string $crawlId): void
    {
        if (!Schema::hasTable('crawl_issues')) {
            return;
        }

        $issues = [];
        $now = now();

        $pages = CrawlPage::query()->where('crawl_id', $crawlId)->get(['id', 'url', 'h1_count', 'alt_missing_count']);
        foreach ($pages as $page) {
            if ((int) $page->alt_missing_count > 0) {
                $issues[] = [
                    'crawl_id' => $crawlId,
                    'crawl_page_id' => $page->id,
                    'type' => 'missing_alt',
                    'url' => $page->url,
                    'meta' => ['missing_alt_count' => (int) $page->alt_missing_count],
                    'created_at' => $now,
                ];
            }

            if ((int) $page->h1_count === 0) {
                $issues[] = [
                    'crawl_id' => $crawlId,
                    'crawl_page_id' => $page->id,
                    'type' => 'missing_h1',
                    'url' => $page->url,
                    'meta' => null,
                    'created_at' => $now,
                ];
            }
        }

        $links = CrawlLink::query()
            ->where('crawl_id', $crawlId)
            ->get(['source_url', 'target_url', 'status_code', 'redirect_chain_length']);

        foreach ($links as $link) {
            if ((int) $link->status_code >= 400) {
                $issues[] = [
                    'crawl_id' => $crawlId,
                    'type' => 'broken_link',
                    'url' => $link->target_url,
                    'meta' => [
                        'source_url' => $link->source_url,
                        'status_code' => (int) $link->status_code,
                    ],
                    'created_at' => $now,
                ];
            }

            if ((int) $link->redirect_chain_length > 1) {
                $issues[] = [
                    'crawl_id' => $crawlId,
                    'type' => 'redirect_chain',
                    'url' => $link->target_url,
                    'meta' => [
                        'source_url' => $link->source_url,
                        'redirect_chain_length' => (int) $link->redirect_chain_length,
                    ],
                    'created_at' => $now,
                ];
            }
        }

        if (empty($issues)) {
            return;
        }

        $allowedColumns = array_flip(Schema::getColumnListing('crawl_issues'));
        $payload = [];

        foreach ($issues as $issue) {
            $row = [];
            foreach ($issue as $key => $value) {
                if (!isset($allowedColumns[$key])) {
                    continue;
                }

                $row[$key] = $key === 'meta' && $value !== null ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value;
            }

            if (!isset($row['crawl_id'], $row['type'])) {
                continue;
            }

            $payload[] = $row;
        }

        if (!empty($payload)) {
            DB::table('crawl_issues')->insert($payload);
        }
    }

    private function persistScanEvent(string $scanId, array $payload): void
    {
        $eventType = $payload['type'] ?? null;
        if (!in_array($eventType, ['crawl_progress', 'page_scanned', 'scan_finished'], true)) {
            return;
        }

        $directory = storage_path("scans/{$scanId}");
        if (!File::exists($directory)) {
            File::ensureDirectoryExists($directory);
        }

        if ($eventType === 'crawl_progress') {
            $progressPayload = [
                'type' => 'crawl_progress',
                'status' => $payload['status'] ?? 'running',
                'stage' => $payload['stage'] ?? 'scanning',
                'current' => (int) ($payload['scanned_pages'] ?? 0),
                'total' => (int) ($payload['total'] ?? config('seo.max_pages', 20)),
                'scanned_pages' => (int) ($payload['scanned_pages'] ?? 0),
                'queue_size' => (int) ($payload['queue_size'] ?? 0),
                'current_url' => $payload['current_url'] ?? null,
            ];

            File::put($directory.'/progress.json', json_encode($progressPayload));

            Crawl::whereKey($scanId)->update([
                'pages_scanned' => (int) ($payload['scanned_pages'] ?? 0),
                'pages_total' => (int) ($payload['total'] ?? config('seo.max_pages', 20)),
                'status' => $payload['status'] ?? 'running',
                'finished_at' => in_array(($payload['status'] ?? ''), ['done', 'failed', 'aborted'], true) ? now() : null,
            ]);

            return;
        }

        if ($eventType === 'scan_finished') {
            $status = (string) ($payload['status'] ?? 'done');

            File::put($directory.'/progress.json', json_encode([
                'type' => 'scan_finished',
                'status' => $status,
                'stage' => 'finished',
                'current' => (int) ($payload['scanned_pages'] ?? 0),
                'total' => (int) ($payload['total'] ?? config('seo.max_pages', 20)),
                'scanned_pages' => (int) ($payload['scanned_pages'] ?? 0),
                'queue_size' => (int) ($payload['queue_size'] ?? 0),
                'current_url' => null,
            ]));

            Crawl::whereKey($scanId)->update([
                'pages_scanned' => (int) ($payload['scanned_pages'] ?? 0),
                'pages_total' => (int) ($payload['total'] ?? config('seo.max_pages', 20)),
                'status' => $status,
                'finished_at' => now(),
            ]);

            return;
        }

        $normalizedUrl = $this->normalizeScanUrl($payload['url'] ?? null);
        $eventPayload = [
            'type' => 'page_scanned',
            'url' => $normalizedUrl,
            'status' => $payload['status'] ?? null,
            'alt_count' => (int) ($payload['alt_count'] ?? 0),
            'heading_count' => (int) ($payload['heading_count'] ?? 0),
            'error' => $payload['error'] ?? null,
        ];

        if (!empty($eventPayload['url'])) {
            $crawlPage = CrawlPage::firstOrNew([
                'crawl_id' => $scanId,
                'url' => $eventPayload['url'],
            ]);

            $crawlPage->fill([
                'status' => $eventPayload['status'],
                'alt_count' => $eventPayload['alt_count'],
                'heading_count' => $eventPayload['heading_count'],
                'error' => $eventPayload['error'],
            ]);

            if (!$crawlPage->exists) {
                $crawlPage->created_at = now();
            }

            $crawlPage->save();

            if (!$crawlPage->wasRecentlyCreated) {
                return;
            }

            File::append($directory.'/events.jsonl', json_encode($eventPayload).PHP_EOL);

            Crawl::whereKey($scanId)->update([
                'pages_scanned' => CrawlPage::where('crawl_id', $scanId)->count(),
            ]);
        }
    }


    private function normalizeScanUrl(?string $rawUrl): ?string
    {
        $candidate = trim((string) $rawUrl);

        if ($candidate === '') {
            return null;
        }

        $parts = parse_url($candidate);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $candidate;
        }

        $path = $parts['path'] ?? '';
        if ($path === '') {
            $path = '/';
        }

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $query = '';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            $filtered = [];

            foreach ($queryParams as $key => $value) {
                if (Str::startsWith(Str::lower((string) $key), 'utm_')) {
                    continue;
                }

                $filtered[$key] = $value;
            }

            if (!empty($filtered)) {
                ksort($filtered);
                $query = http_build_query($filtered);
            }
        }

        $normalized = sprintf(
            '%s://%s%s%s%s',
            Str::lower((string) $parts['scheme']),
            Str::lower((string) $parts['host']),
            isset($parts['port']) ? ':'.$parts['port'] : '',
            $path,
            $query !== '' ? '?'.$query : ''
        );

        return $normalized;
    }

    private function runMultiScan(Scan $scan, ReportPersistenceService $reportPersistenceService): void
    {
        Log::info('[SCAN] Job gestartet', [
            'scan_id' => $scan->id,
            'url' => $scan->url,
        ]);

        $scan->update(['status' => 'running']);

        Crawl::whereKey($scan->id)->update(['status' => 'running']);

        Storage::makeDirectory("scans/{$scan->id}");
        Storage::put("scans/{$scan->id}/progress.json", json_encode([
            'type' => 'crawl_progress',
            'status' => 'running',
            'stage' => 'crawling',
            'current' => 0,
            'total' => (int) config('seo.max_pages', 20),
            'scanned_pages' => 0,
            'queue_size' => 0,
            'current_url' => $scan->url,
        ]));

        $options = [
            'scan_id' => $scan->id,
            'url' => $scan->url,
            'checks' => $this->checks,
            'max_pages' => config('seo.max_pages', 20),
            'max_depth' => config('seo.max_depth', 2),
            'page_timeout' => config('seo.page_timeout', 30),
            'max_parallel_pages' => (int) env('CRAWLER_CONCURRENCY', config('seo.crawler_concurrency', 6)),
            'max_retries' => min((int) config('seo.max_retries', 2), 2),
            'retry_delay' => config('seo.retry_delay', 10),
            'max_scan_time' => config('seo.max_scan_time', 300),
            'concurrency' => (int) env('CRAWLER_CONCURRENCY', 6),
        ];

        $command = sprintf(
            'node %s %s %s',
            escapeshellarg(base_path('node-scanner/core/multiScanner.js')),
            escapeshellarg(json_encode($options, JSON_UNESCAPED_SLASHES)),
            escapeshellarg($this->scanId)
        );

        $process = Process::fromShellCommandline($command, base_path());

        $process->setTimeout(null);
        Log::debug('[SCAN TRACE] node_process_start', [
            'scan_id' => $scan->id,
            'target' => 'multiScanner.js',
        ]);
        Log::info('[SCAN] Starting Node Scanner', ['scan_id' => $scan->id]);
        Log::debug('[SCAN TRACE] node_command', [
            'scan_id' => $scan->id,
            'command' => $process->getCommandLine(),
        ]);
        Log::debug('[SCAN] Node command', ['command' => $process->getCommandLine()]);
        $stdoutBuffer = '';

        try {
            $process->run(function (string $type, string $buffer) use ($scan, &$stdoutBuffer): void {
                $trimmed = trim($buffer);

                if ($type === Process::ERR) {
                    Log::error('[NODE STDERR]', [
                        'scan_id' => $scan->id,
                        'output' => $trimmed,
                    ]);

                    return;
                }

                $stdoutBuffer .= $buffer;
                $lines = preg_split('/\r?\n/', $stdoutBuffer) ?: [];
                $stdoutBuffer = array_pop($lines) ?? '';

                foreach ($lines as $line) {
                    if ($line === '') {
                        continue;
                    }

                    $payload = json_decode($line, true);
                    if (is_array($payload)) {
                        $this->persistScanEvent($scan->id, $payload);
                    }
                }

                Log::debug('[NODE STDOUT]', [
                    'scan_id' => $scan->id,
                    'output' => $trimmed,
                ]);
            });

            $finalLine = trim($stdoutBuffer);
            if ($finalLine !== '') {
                $payload = json_decode($finalLine, true);
                if (is_array($payload)) {
                    $this->persistScanEvent($scan->id, $payload);
                }
            }
        } catch (\Throwable $e) {
            Log::error('[SCAN TRACE] node_process_exception', [
                'scan_id' => $scan->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
        Log::debug('[SCAN TRACE] node_process_finished', [
            'scan_id' => $scan->id,
            'exit_code' => $process->getExitCode(),
            'exit_code_text' => $process->getExitCodeText(),
            'successful' => $process->isSuccessful(),
        ]);
        Log::info('[SCAN] Node Scanner finished', ['scan_id' => $scan->id]);

        if (!$process->isSuccessful()) {
            Log::error('RunScan Node Prozess fehlgeschlagen', [
                'scan_id' => $this->scanId,
                'error' => $process->getErrorOutput(),
            ]);

            $scan->update(['status' => 'failed']);
            Crawl::whereKey($scan->id)->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);
            return;
        }

        $report = Report::create([
            'id' => $scan->id,
            'url' => $scan->url,
            'type' => 'crawler',
            'status' => 'processing',
        ]);

        $reportPersistenceService->syncFromStorage($report);

        $report->load('results');
        app(IssueDetectionService::class)->detectAndStoreForReport($report);

        $scan->update(['status' => 'done']);

        Crawl::whereKey($scan->id)->update([
            'status' => 'done',
            'finished_at' => now(),
        ]);
    }
}
