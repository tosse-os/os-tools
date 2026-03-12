<?php

return [
  'max_pages' => 20,
  'max_depth' => 2,
  'page_timeout' => 30,
  'max_parallel_pages' => (int) env('SCAN_CONCURRENCY', 8),
  'crawler_concurrency' => (int) env('CRAWLER_CONCURRENCY', 6),
  'max_retries' => 2,
  'retry_delay' => 10,
  'max_scan_time' => 300,
  'crawler_use_redis' => false,
];
