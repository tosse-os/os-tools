<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
  public function index()
  {
    $logPath = storage_path('logs/laravel.log');

    if (!File::exists($logPath)) {
      return view('logs.index', ['entries' => []]);
    }

    $lines = array_reverse(file($logPath));
    $entries = [];

    foreach ($lines as $line) {

      if (preg_match('/^\[(.*?)\]\s+(\w+)\.(\w+):\s+(.*)$/', $line, $matches)) {

        $level = strtolower($matches[3]);

        if (!in_array($level, ['error', 'warning'])) {
          continue;
        }

        $message = $matches[4];

        if (str_contains($message, 'Stacktrace')) {
          continue;
        }

        $entries[] = [
          'time' => $matches[1],
          'level' => $level,
          'message' => $message,
        ];
      }

      if (count($entries) >= 200) {
        break;
      }
    $lines = File::lines($logPath)->toArray();
    $lines = array_slice($lines, -400);

    $entries = [];

    foreach ($lines as $line) {
      if (preg_match('/^\[(.*?)\]\s+(\w+)\.(\w+):\s+(.*)$/', $line, $matches)) {
        $entries[] = [
          'timestamp' => $matches[1],
          'environment' => $matches[2],
          'level' => strtoupper($matches[3]),
          'message' => $matches[4],
        ];
      }
    }

    return view('logs.index', compact('entries'));
  }

  public function raw(): JsonResponse
  {
    $logPath = storage_path('logs/laravel.log');

    if (!File::exists($logPath)) {
      return response()->json(['lines' => []]);
    }

    $lines = File::lines($logPath)->toArray();

    return response()->json([
      'lines' => array_slice($lines, -200),
    ]);
  }
}
