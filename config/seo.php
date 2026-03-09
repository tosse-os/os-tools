<?php

return [
  'max_pages' => 20,
  'max_depth' => 2,
  'page_timeout' => 30,
  'max_parallel_pages' => (int) env('SCAN_CONCURRENCY', 3),
  'max_retries' => 3,
  'retry_delay' => 10,
  'max_scan_time' => 300,
];
