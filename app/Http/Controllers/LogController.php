<?php

namespace App\Http\Controllers;

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
    }

    return view('logs.index', compact('entries'));
  }
}
