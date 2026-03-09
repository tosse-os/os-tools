<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Report;

class IssueDetectionService
{
    private const TYPE_SEVERITY_MAP = [
        'missing_h1' => Issue::SEVERITY_CRITICAL,
        'duplicate_title' => Issue::SEVERITY_WARNING,
        'thin_content' => Issue::SEVERITY_WARNING,
        'missing_schema' => Issue::SEVERITY_WARNING,
        'missing_alt' => Issue::SEVERITY_INFO,
    ];

    public function detectAndStoreForReport(Report $report): void
    {
        $issues = $this->detectFromReportResults($report->results->pluck('payload')->all());

        $report->issues()->delete();

        if (empty($issues)) {
            return;
        }

        $report->issues()->createMany(array_map(function (array $issue) use ($report) {
            return [
                'report_id' => $report->id,
                'url' => $issue['url'] ?? $report->url,
                'type' => $issue['type'],
                'severity' => $issue['severity'],
                'message' => $issue['message'],
                'created_at' => now(),
            ];
        }, $issues));
    }

    public function detectFromReportResults(array $reportResultsPayload): array
    {
        $issues = [];
        $pages = [];

        foreach ($reportResultsPayload as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $pages = array_merge($pages, $this->extractPagesFromPayload($payload));
        }

        if (empty($pages)) {
            return [];
        }

        $titleUsage = [];
        foreach ($pages as $page) {
            $normalizedTitle = trim((string) ($page['title'] ?? ''));
            if ($normalizedTitle === '') {
                continue;
            }

            $titleUsage[mb_strtolower($normalizedTitle)][] = $page['url'];
        }

        foreach ($pages as $page) {
            $url = $page['url'] ?? null;
            $h1 = is_array($page['h1']) ? $page['h1'] : [];
            $title = trim((string) ($page['title'] ?? ''));
            $content = trim((string) ($page['content'] ?? ''));
            $schema = is_array($page['schema']) ? $page['schema'] : [];
            $altCheck = is_array($page['alt_check']) ? $page['alt_check'] : [];

            if (count($h1) === 0) {
                $issues[] = $this->buildIssue($url, 'missing_h1', 'Missing H1 heading.');
            }

            if ($title !== '' && count($titleUsage[mb_strtolower($title)] ?? []) > 1) {
                $issues[] = $this->buildIssue($url, 'duplicate_title', 'Duplicate page title detected.');
            }

            if (mb_strlen($content) > 0 && mb_strlen($content) < 300) {
                $issues[] = $this->buildIssue($url, 'thin_content', 'Thin content detected.');
            }

            if (empty($schema)) {
                $issues[] = $this->buildIssue($url, 'missing_schema', 'No schema markup detected.');
            }

            $altMissing = (int) ($altCheck['altMissing'] ?? 0);
            $altEmpty = (int) ($altCheck['altEmpty'] ?? 0);
            if ($altMissing > 0 || $altEmpty > 0) {
                $issues[] = $this->buildIssue($url, 'missing_alt', 'Images with missing or empty alt text detected.');
            }
        }

        return $issues;
    }

    private function extractPagesFromPayload(array $payload): array
    {
        if (array_key_exists('title', $payload) || array_key_exists('h1', $payload) || array_key_exists('content', $payload)) {
            return [[
                'url' => $payload['url'] ?? null,
                'title' => $payload['title'] ?? '',
                'h1' => $payload['h1'] ?? [],
                'content' => $payload['content'] ?? '',
                'schema' => $payload['schema'] ?? [],
                'alt_check' => $payload['altCheck'] ?? [],
            ]];
        }

        if (isset($payload['breakdown']) && is_array($payload['breakdown'])) {
            return [[
                'url' => $payload['url'] ?? null,
                'title' => data_get($payload, 'title', ''),
                'h1' => data_get($payload, 'breakdown.h1.checks.exists') ? ['present'] : [],
                'content' => str_repeat('x', (int) data_get($payload, 'breakdown.content.score', 0) * 30),
                'schema' => data_get($payload, 'breakdown.schema.score', 0) > 0 ? ['present'] : [],
                'alt_check' => [],
            ]];
        }

        return [];
    }

    private function buildIssue(?string $url, string $type, string $message): array
    {
        return [
            'url' => $url,
            'type' => $type,
            'severity' => self::TYPE_SEVERITY_MAP[$type] ?? Issue::SEVERITY_INFO,
            'message' => $message,
        ];
    }
}
