<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class LogController extends Controller
{
    private const ALLOWED_LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical'];
    private const DEFAULT_TAIL_LINES = 1000;
    private const DEFAULT_MAX_ENTRIES_PER_LOG = 300;
    private const REVERSE_READ_CHUNK_BYTES = 8192;

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
        if (! is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $entries = [];
        $current = null;
        $source = basename($path);
        $lineLimit = max(1, (int) config('logs.viewer_tail_lines', self::DEFAULT_TAIL_LINES));
        $entryLimit = max(1, (int) config('logs.viewer_entry_limit', self::DEFAULT_MAX_ENTRIES_PER_LOG));

        try {
            $startOffset = $this->findTailStartOffset($handle, $lineLimit);
            fseek($handle, $startOffset);

            while (($line = fgets($handle)) !== false) {
                $line = preg_replace('/\e\[[\d;]*m/', '', (string) $line);
                $line = trim((string) $line);

                if (preg_match('/^\[(.*?)\]\s+([^\.]+)\.([A-Z]+):\s+(.*)$/', $line, $m)) {
                    if ($current) {
                        $entries[] = $current;

                        if (count($entries) >= $entryLimit) {
                            break;
                        }
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

            if ($current && count($entries) < $entryLimit) {
                $entries[] = $current;
            }
        } finally {
            fclose($handle);
        }

        return $entries;
    }

    private function findTailStartOffset($handle, int $lineLimit): int
    {
        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);

        if ($fileSize === false || $fileSize <= 0) {
            return 0;
        }

        $position = $fileSize;
        $newlinesFound = 0;

        while ($position > 0) {
            $readSize = min(self::REVERSE_READ_CHUNK_BYTES, $position);
            $position -= $readSize;

            fseek($handle, $position);
            $chunk = fread($handle, $readSize);

            if ($chunk === false || $chunk === '') {
                break;
            }

            for ($i = strlen($chunk) - 1; $i >= 0; $i--) {
                if ($chunk[$i] !== "\n") {
                    continue;
                }

                $newlinesFound++;

                if ($newlinesFound > $lineLimit) {
                    return $position + $i + 1;
                }
            }
        }

        return 0;
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
