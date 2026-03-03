<?php

namespace App\Services\Scoring;

class PriorityFactory
{
  public static function make(
    string $code,
    string $severity,
    string $category,
    string $message
  ): array {
    return [
      'code' => $code,
      'severity' => $severity,
      'category' => $category,
      'message' => $message
    ];
  }
}
