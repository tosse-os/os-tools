<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class LogController extends Controller
{
    private const ALLOWED_LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical'];

    /** @return array<int, array<string, mixed>> */
    protected function readLogs(?string $level = null): array
    {
        $paths = collect(File::glob(storage_path('logs/*.log')) ?: [])
            ->filter(fn (string $path) => str_ends_with($path, '.log'))
            ->values();

        $entries = [];

        foreach ($paths as $path) {
            $entries = array_merge($entries, $this->parseLogFile($path));
        }

        if ($level && in_array($level, self::ALLOWED_LEVELS, true)) {
            $entries = array_values(array_filter($entries, fn (array $entry) => ($entry['level'] ?? 'info') === $level));
        }

        usort($entries, static function (array $a, array $b) {
            return strtotime((string) ($b['timestamp'] ?? '')) <=> strtotime((string) ($a['timestamp'] ?? ''));
        });

        return $entries;
    }

    /** @return array<int, array<string, mixed>> */
    private function parseLogFile(string $path): array
    {
        $lines = @file($path) ?: [];
        $entries = [];
        $current = null;
        $source = basename($path);

        foreach ($lines as $line) {
            $line = preg_replace('/\e\[[\d;]*m/', '', (string) $line);
            $line = trim((string) $line);

            if (preg_match('/^\[(.*?)\]\s+([^\.]+)\.([A-Z]+):\s+(.*)$/', $line, $m)) {
                if ($current) {
                    $entries[] = $current;
                }

                $current = [
                    'timestamp' => $m[1],
                    'environment' => $m[2],
                    'level' => strtolower($m[3]),
                    'message' => $m[4],
                    'trace' => [],
                    'source' => $source,
                ];

                continue;
            }

            if ($current) {
                $current['trace'][] = $line;
            }
        }

        if ($current) {
            $entries[] = $current;
        }

        return $entries;
    }

    public function index(Request $request): View|JsonResponse
    {
        $level = strtolower((string) $request->query('level', 'all'));
        $filterLevel = $level === 'all' ? null : $level;
        $entries = array_slice($this->readLogs($filterLevel), 0, 200);

        if ($request->ajax()) {
            return response()->json([
                'html' => view('logs.partials.table', ['entries' => $entries])->render(),
                'count' => count($entries),
            ]);
        }

        return view('logs.index', [
            'entries' => $entries,
            'level' => $level,
            'allowedLevels' => self::ALLOWED_LEVELS,
        ]);
    }

    public function raw(Request $request): JsonResponse
    {
        $level = strtolower((string) $request->query('level', 'all'));

        return response()->json($this->readLogs($level === 'all' ? null : $level));
    }

    public function clear(Request $request)
    {
        File::put(storage_path('logs/laravel.log'), '');

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return redirect()->route('logs.live');
    }
}
