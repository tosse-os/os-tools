#!/usr/bin/env node

const crypto = require('node:crypto');
const path = require('path');
const Redis = require('ioredis');
const { absoluteUrl } = require('../utils/urlUtils');
const { createStructuredLogger } = require('../utils/structuredLogger');

const logFile = path.resolve(__dirname, '..', '..', 'storage', 'logs', 'node-scanner.log');
const logger = createStructuredLogger({ logFilePath: logFile, stderr: process.stderr, stdout: process.stderr });

const redis = new Redis(process.env.REDIS_URL || undefined);
const concurrency = Math.max(1, Number(process.env.CRAWLER_CONCURRENCY || 4));
const active = new Set();
const runtimeMode = process.env.CRAWLER_RUNTIME_MODE || 'redis';
const httpBaseUrl = process.env.CRAWLER_HTTP_BASE_URL || 'http://127.0.0.1:8000';
const httpCrawlId = process.env.CRAWLER_ID || '';
const httpStartUrl = process.env.CRAWLER_START_URL || '';
const localQueue = [];
const seenUrls = new Set();

logger.info('worker_process_started', {
  runtime_mode: runtimeMode,
  concurrency,
  http_base_url: httpBaseUrl,
  crawl_id: httpCrawlId || null,
  start_url: httpStartUrl || null,
});

process.on('uncaughtException', (err) => {
  logger.error('uncaught_exception', { error: err?.message || String(err), stack: err?.stack || null });
});

process.on('unhandledRejection', (err) => {
  logger.error('unhandled_rejection', { error: err?.message || String(err), stack: err?.stack || null });
});

process.on('exit', (code) => {
  logger.info('process_exit', { code });
});

let lastEventAt = Date.now();
let heartbeatWarnAt = 0;

function nowIso() {
  return new Date().toISOString();
}

function hash(input = '') {
  return crypto.createHash('sha1').update(String(input), 'utf8').digest('hex');
}

function stripHtml(input = '') {
  return String(input).replace(/<script[\s\S]*?<\/script>/gi, ' ').replace(/<style[\s\S]*?<\/style>/gi, ' ').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

function extractLinks(html = '') {
  return Array.from(String(html).matchAll(/<a\s+[^>]*href=["']([^"']+)["'][^>]*>/gi)).map((m) => m[1]);
}

async function emit(event) {
  lastEventAt = Date.now();
  logger.debug('event_emitted', {
    crawl_id: event.crawl_id,
    type: event.type,
    mode: runtimeMode,
  });

  if (runtimeMode === 'http') {
    await fetch(`${httpBaseUrl}/internal/crawler/event`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(event),
    });
    return;
  }

  await redis.lpush('crawl:event_queue', JSON.stringify(event));
}

async function markQueue(crawlId, url, status) {
  logger.debug('queue_status', { crawl_id: crawlId, url, status, active_workers: active.size, local_queue: localQueue.length });
  await redis.lpush('crawl:event_queue', JSON.stringify({
    crawl_id: crawlId,
    type: 'crawl_progress',
    timestamp: nowIso(),
    payload: { url, status },
  }));
}

async function crawlUrl(task) {
  const started = Date.now();
  const { crawl_id: crawlId, url, depth = 0 } = task;

  logger.info('url_crawled', { crawl_id: crawlId, url, depth });
  await markQueue(crawlId, url, 'processing');

  let statusCode = 0;
  let html = '';
  let finalUrl = url;

  const urlTimeoutHandle = setTimeout(() => {
    logger.warn('crawler_timeout_detected', { crawl_id: crawlId, url, timeout_ms: 30000 });
  }, 30000);

  try {
    const response = await fetch(url, { redirect: 'follow', headers: { 'user-agent': 'OS-Crawler-Worker/2.0' } });
    statusCode = response.status;
    html = await response.text();
    finalUrl = response.url || url;
  } catch (error) {
    logger.error('fetch_failed', { crawl_id: crawlId, url, error: error?.message || String(error) });
    statusCode = 599;
  } finally {
    clearTimeout(urlTimeoutHandle);
  }

  const text = stripHtml(html);
  const links = extractLinks(html).map((link) => absoluteUrl(finalUrl, link)).filter(Boolean);
  const rootHost = (() => { try { return new URL(url).host; } catch { return ''; } })();
  const internalLinks = links.filter((link) => { try { return new URL(link).host === rootHost; } catch { return false; } });
  const externalLinks = links.filter((link) => { try { return new URL(link).host !== rootHost; } catch { return false; } });

  logger.debug('links_extracted', {
    crawl_id: crawlId,
    url,
    extracted_total: links.length,
    internal_count: internalLinks.length,
    external_count: externalLinks.length,
  });

  await emit({
    crawl_id: crawlId,
    type: 'page_crawled',
    timestamp: nowIso(),
    payload: {
      url,
      depth,
      status_code: statusCode,
      title: (html.match(/<title[^>]*>([\s\S]*?)<\/title>/i) || [])[1] || null,
      meta_description: (html.match(/<meta\s+name=["']description["']\s+content=["']([^"']*)["']/i) || [])[1] || null,
      h1_count: (html.match(/<h1\b/gi) || []).length,
      alt_missing_count: (html.match(/<img\b(?![^>]*\balt=)[^>]*>/gi) || []).length,
      internal_links_count: internalLinks.length,
      external_links_count: externalLinks.length,
      word_count: text ? text.split(/\s+/).length : 0,
      content_hash: hash(html),
      text_hash: hash(text),
      response_time: Date.now() - started,
      text_content: text,
      content: html,
    },
  });

  for (const targetUrl of links) {
    const type = (() => {
      try {
        return new URL(targetUrl).host === rootHost ? 'internal' : 'external';
      } catch {
        return 'external';
      }
    })();

    await emit({
      crawl_id: crawlId,
      type: 'link_discovered',
      timestamp: nowIso(),
      payload: {
        crawl_id: crawlId,
        source_url: url,
        target_url: targetUrl,
        type,
        status_code: null,
        redirect_chain_length: 0,
      },
    });

    if (type === 'internal') {
      if (runtimeMode === 'http' && !seenUrls.has(targetUrl)) {
        seenUrls.add(targetUrl);
        localQueue.push({ crawl_id: crawlId, url: targetUrl, depth: depth + 1 });
      }

      await emit({
        crawl_id: crawlId,
        type: 'url_discovered',
        timestamp: nowIso(),
        payload: {
          url: targetUrl,
          depth: depth + 1,
        },
      });
    }
  }

  await markQueue(crawlId, url, 'done');
}

async function loop() {
  if (runtimeMode === 'http') {
    if (httpStartUrl && httpCrawlId) {
      seenUrls.add(httpStartUrl);
      localQueue.push({ crawl_id: httpCrawlId, url: httpStartUrl, depth: 0 });
      await emit({
        crawl_id: httpCrawlId,
        type: 'crawl_started',
        timestamp: nowIso(),
        payload: { url: httpStartUrl },
      });
    }
  }

  while (true) {
    if (Date.now() - lastEventAt > 60000 && Date.now() - heartbeatWarnAt > 60000) {
      heartbeatWarnAt = Date.now();
      logger.warn('worker_event_heartbeat_timeout', {
        runtime_mode: runtimeMode,
        idle_ms: Date.now() - lastEventAt,
        active_workers: active.size,
        local_queue_size: localQueue.length,
      });
    }

    while (active.size >= concurrency) {
      await Promise.race(active);
    }

    let task;
    if (runtimeMode === 'http') {
      task = localQueue.shift();
      if (!task) {
        if (active.size === 0) {
          await emit({
            crawl_id: httpCrawlId,
            type: 'crawl_finished',
            timestamp: nowIso(),
            payload: { worker: process.env.CRAWLER_WORKER_ID || '1' },
          });
          process.exit(0);
        }

        await new Promise((resolve) => setTimeout(resolve, 200));
        continue;
      }
    } else {
      const result = await redis.brpop('crawl:url_queue', 2);
      if (!result || !result[1]) {
        logger.debug('queue_status', { source: 'redis', message: 'no_task', active_workers: active.size });
        continue;
      }

      try {
        task = JSON.parse(result[1]);
      } catch (error) {
        logger.error('invalid_queue_payload', { payload: result[1], error: error?.message || String(error) });
        continue;
      }
    }

    if (task.type === 'crawl_stop') {
      await emit({
        crawl_id: task.crawl_id,
        type: 'crawl_finished',
        timestamp: nowIso(),
        payload: { worker: process.env.CRAWLER_WORKER_ID || '1' },
      });
      process.exit(0);
    }

    const promise = crawlUrl(task).finally(() => active.delete(promise));
    active.add(promise);
  }
}

loop().catch(async (error) => {
  logger.error('worker_loop_failed', { error: error?.message || String(error), stack: error?.stack || null });
  await emit({
    crawl_id: 'unknown',
    type: 'crawl_progress',
    timestamp: nowIso(),
    payload: { error: String(error.message || error) },
  });
  process.exit(1);
});
