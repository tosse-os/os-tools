# Event-Driven Crawler Architecture

## Overview

The crawler subsystem is now independent from Local SEO scan flows.

Pipeline:

1. Node worker (`node-scanner/core/crawler-worker.js`) fetches URLs from `crawl:url_queue`.
2. Worker emits structured events to `crawl:event_queue`.
3. Laravel `CrawlerEventConsumer` consumes events.
4. Persistence services upsert page/link/event/queue data.
5. `/crawls/{id}` continues reading from `crawls`, `crawl_pages`, and `crawl_links`.

## Redis queues

- `crawl:url_queue`
- `crawl:event_queue`

## Event types

- `crawl_started`
- `url_discovered`
- `page_crawled`
- `link_discovered`
- `crawl_progress`
- `crawl_finished`

## Laravel services (`app/Services/Crawler`)

- `CrawlerEventConsumer`
- `CrawlPagePersister`
- `CrawlLinkPersister`
- `CrawlMetricsService`
- `CrawlProgressService`

## Persistence guarantees

- Atomic writes use `upsert` / `updateOrInsert`.
- No `firstOrNew` in the new crawler persistence flow.
- Progress uses counter increments (`pages_discovered`, `pages_scanned`, `pages_failed`).

## Schema updates

- Expanded `crawls` counters and timestamps for scalable progress tracking.
- Added `crawl_events` (event store).
- Added `crawl_queue` (discovery/work state).
- Added hash indexes on `crawl_pages` for duplicate-content detection.
- Added query indexes on `crawl_links` for link analytics.
