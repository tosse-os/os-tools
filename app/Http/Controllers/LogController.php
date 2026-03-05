<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LogController extends Controller
{
  protected function readLog()
  {
    $path = storage_path('logs/laravel.log');

    if (!file_exists($path)) {
      return [];
    }

    $lines = file($path);

    $entries = [];
    $current = null;

    foreach ($lines as $line) {

      $line = preg_replace('/\e\[[\d;]*m/', '', $line);
      $line = trim($line);

      if (preg_match('/^\[(.*?)\]\s+(\w+)\.(\w+):\s+(.*)$/', $line, $m)) {

        if ($current) {
          $entries[] = $current;
        }

        $current = [
          'timestamp' => $m[1],
          'environment' => $m[2],
          'level' => strtolower($m[3]),
          'message' => $m[4],
          'trace' => []
        ];
      } else {

        if ($current) {
          $current['trace'][] = $line;
        }
      }
    }

    if ($current) {
      $entries[] = $current;
    }

    return array_reverse($entries);
  }

  public function index()
  {
    $entries = $this->readLog();

    return view('logs.index', [
      'entries' => array_slice($entries, 0, 200)
    ]);
  }

  public function raw()
  {
    return response()->json($this->readLog());
  }
}
