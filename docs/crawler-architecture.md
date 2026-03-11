# Crawler Subsystem Architecture (Event-Driven Rewrite)

## Overview

The crawler is now an independent subsystem based on Redis queues and event consumption:

`Node crawler-worker -> Redis crawl:event_queue -> Laravel CrawlerEventConsumer -> persistence services -> database`

## Queues

- `crawl:url_queue`: pending URLs for worker nodes.
- `crawl:event_queue`: structured crawl events consumed by Laravel.

## Event Types

Each event contains:

- `crawl_id`
- `timestamp`
- `payload`

Supported event types:

- `crawl_started`
- `url_discovered`
- `page_crawled`
- `link_discovered`
- `crawl_progress`
- `crawl_finished`

## Laravel Services

Located in `app/Services/Crawler`:

- `CrawlerEventConsumer`
- `CrawlPagePersister`
- `CrawlLinkPersister`
- `CrawlMetricsService`
- `CrawlProgressService`

These services use atomic persistence (`upsert`, `updateOrInsert`, `insertOrIgnore`) and atomic counters (`pages_scanned`, `pages_discovered`, `pages_failed`).

## Database Model

Normalized tables:

- `crawls`
- `crawl_pages`
- `crawl_links`
- `crawl_events`
- `crawl_queue`

Indexes were added for high-volume querying and duplicate detection (`crawl_id + url`, `crawl_id + content_hash`, `crawl_id + text_hash`).

## Runtime

1. Laravel dispatches `RunCrawlPipeline` to seed crawl start events.
2. Node `crawler-worker.js` workers process `crawl:url_queue`.
3. Workers emit events to `crawl:event_queue`.
4. Laravel consumes events with `php artisan crawl:consume-events`.
5. `/crawls/{id}` continues to render persisted crawl data.

## Notes

- Crawler progress is database-backed (no file-based progress dependency in crawler pipeline).
- Local SEO scanner subsystem is intentionally untouched.
