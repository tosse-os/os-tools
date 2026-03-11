const crypto = require('crypto');
const { URL } = require('url');
const Redis = require('ioredis');

const redis = new Redis(process.env.REDIS_URL || undefined);
const urlQueue = 'crawl:url_queue';
const eventQueue = 'crawl:event_queue';

function hash(input) {
  return crypto.createHash('sha256').update(String(input || ''), 'utf8').digest('hex');
}

function stripTags(html) {
  return String(html || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

function extractMetrics(html, pageUrl) {
  const safeHtml = String(html || '');
  const titleMatch = safeHtml.match(/<title[^>]*>([\s\S]*?)<\/title>/i);
  const metaDescription = safeHtml.match(/<meta\s+name=["']description["']\s+content=["']([\s\S]*?)["'][^>]*>/i);
  const h1Count = (safeHtml.match(/<h1\b[^>]*>/gi) || []).length;
  const images = Array.from(safeHtml.matchAll(/<img\b[^>]*>/gi));
  const altMissingCount = images.filter((m) => !/\salt\s*=/.test(m[0])).length;
  const links = Array.from(safeHtml.matchAll(/<a\s+[^>]*href=["']([^"']+)["'][^>]*>/gi)).map((m) => m[1]);

  let internalLinksCount = 0;
  let externalLinksCount = 0;

  for (const link of links) {
    try {
      const resolved = new URL(link, pageUrl);
      const current = new URL(pageUrl);
      if (resolved.hostname === current.hostname) internalLinksCount += 1;
      else externalLinksCount += 1;
    } catch {
      internalLinksCount += 1;
    }
  }

  const text = stripTags(safeHtml);

  return {
    title: titleMatch ? stripTags(titleMatch[1]) : null,
    meta_description: metaDescription ? stripTags(metaDescription[1]) : null,
    h1_count: h1Count,
    alt_missing_count: altMissingCount,
    internal_links_count: internalLinksCount,
    external_links_count: externalLinksCount,
    word_count: text ? text.split(/\s+/).length : 0,
    content_hash: hash(safeHtml),
    text_hash: hash(text),
    discovered_links: links,
  };
}

async function emit(crawlId, type, payload = {}) {
  await redis.lpush(eventQueue, JSON.stringify({
    crawl_id: crawlId,
    type,
    timestamp: new Date().toISOString(),
    payload,
  }));
}

async function crawlUrl(job) {
  const crawlId = job.crawl_id;
  const url = job.url;
  const depth = Number(job.depth || 0);

  const startedAt = Date.now();

  try {
    const response = await fetch(url, { redirect: 'follow', headers: { 'user-agent': 'OS-Crawler-Worker/2.0' } });
    const html = await response.text();
    const responseTime = Date.now() - startedAt;
    const metrics = extractMetrics(html, url);

    await emit(crawlId, 'page_crawled', {
      url,
      depth,
      status_code: response.status,
      response_time: responseTime,
      ...metrics,
    });

    for (const href of metrics.discovered_links) {
      let target;
      try {
        target = new URL(href, url).toString();
      } catch {
        continue;
      }

      const type = new URL(target).hostname === new URL(url).hostname ? 'internal' : 'external';

      await emit(crawlId, 'link_discovered', {
        source_url: url,
        target_url: target,
        type,
        status_code: null,
        redirect_chain_length: 0,
      });

      if (type === 'internal') {
        await emit(crawlId, 'url_discovered', {
          url: target,
          depth: depth + 1,
        });
      }
    }

    await emit(crawlId, 'crawl_progress', {
      status: 'running',
      current_url: url,
    });
  } catch (error) {
    await emit(crawlId, 'page_crawled', {
      url,
      depth,
      status_code: 500,
      response_time: Date.now() - startedAt,
      title: null,
      meta_description: null,
      h1_count: 0,
      alt_missing_count: 0,
      internal_links_count: 0,
      external_links_count: 0,
      word_count: 0,
      content_hash: null,
      text_hash: null,
      error: error.message,
    });
  }
}

async function runWorker() {
  while (true) {
    const result = await redis.brpop([urlQueue], 5);
    if (!result || !result[1]) {
      continue;
    }

    let job;
    try {
      job = JSON.parse(result[1]);
    } catch {
      continue;
    }

    if (!job.crawl_id || !job.url) {
      continue;
    }

    await crawlUrl(job);
  }
}

runWorker();
