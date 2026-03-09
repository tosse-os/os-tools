const { URL } = require('url');
const { normalizeUrl } = require('../utils/urlUtils');

function createLogger(logger) {
  return typeof logger === 'function' ? logger : () => {};
}

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function toPositiveInt(value, fallback) {
  const num = Number(value);
  return Number.isFinite(num) && num > 0 ? Math.floor(num) : fallback;
}

function toPositiveMs(value, fallbackMs) {
  const num = Number(value);
  return Number.isFinite(num) && num > 0 ? num : fallbackMs;
}

function toPatternPath(pathname) {
  return pathname
    .replace(/\b\d{4}\b/g, ':year')
    .replace(/\b\d{1,2}\b/g, ':num')
    .replace(/\b\d+\b/g, ':num');
}

function shouldBlockLoopByPathPattern(urlString, loopState, limits) {
  let parsed;

  try {
    parsed = new URL(urlString);
  } catch {
    return false;
  }

  const pathname = parsed.pathname || '/';
  const signature = toPatternPath(pathname);

  if (!loopState.has(signature)) {
    loopState.set(signature, new Set([pathname]));
    return false;
  }

  const variants = loopState.get(signature);

  if (!variants.has(pathname)) {
    variants.add(pathname);
  }

  return variants.size > limits.maxPatternVariants;
}

module.exports = async function crawlLinks(page, startUrl, options = {}) {
  const log = createLogger(options.logger);
  const maxPages = toPositiveInt(options.max_pages ?? options.maxPages, 10);
  const maxDepth = toPositiveInt(options.max_depth ?? options.maxDepth, 2);
  const maxScanTimeMs = toPositiveMs(options.max_scan_time ?? options.maxScanTime, 300000);
  const pageTimeoutSeconds = toPositiveInt(options.page_timeout ?? options.pageTimeout, 30);
  const maxRetries = toPositiveInt(options.max_retries ?? options.maxRetries, 3);
  const retryDelaySeconds = toPositiveInt(options.retry_delay ?? options.retryDelay, 10);
  const includeLinkGraph = options.include_link_graph === true || options.includeLinkGraph === true;

  const pageTimeoutMs = pageTimeoutSeconds * 1000;
  const retryDelayMs = retryDelaySeconds * 1000;
  const maxQueueSize = Math.max(maxPages * 4, 100);
  const maxLinksPerPage = Math.max(Math.min(maxPages, 100), 20);
  const loopLimits = {
    maxPatternVariants: Math.max(Math.min(maxPages, 25), 8),
  };

  const normalizedStartUrl = normalizeUrl(startUrl);
  log(`[crawlLinks] scan start | start_url=${startUrl} | normalized_start_url=${normalizedStartUrl || 'invalid'} | max_pages=${maxPages} | max_depth=${maxDepth}`);

  if (!normalizedStartUrl) {
    return includeLinkGraph ? {
      urls: [],
      internal_links: [],
      page_depth: {},
      incoming_links_count: {},
      outgoing_links_count: {},
      pages: [],
      orphan_pages: [],
    } : [];
  }

  const visitedUrls = new Set();
  const discoveredUrls = new Set([normalizedStartUrl]);
  const queued = new Set([normalizedStartUrl]);
  const queueByDepth = Array.from({ length: maxDepth + 1 }, (_, depth) => depth === 0 ? [normalizedStartUrl] : []);
  const depthByUrl = new Map([[normalizedStartUrl, 0]]);
  const crawlStartedAt = Date.now();
  const loopPatternState = new Map();
  const outgoingLinks = new Map();
  const incomingCounts = new Map();

  let stopReason = null;

  let origin;
  try {
    origin = new URL(normalizedStartUrl).origin;
  } catch {
    return includeLinkGraph ? {
      urls: [],
      internal_links: [],
      page_depth: {},
      incoming_links_count: {},
      outgoing_links_count: {},
      pages: [],
      orphan_pages: [],
    } : [];
  }

  const ensureOutgoingSet = (url) => {
    if (!outgoingLinks.has(url)) {
      outgoingLinks.set(url, new Set());
    }
    return outgoingLinks.get(url);
  };

  const ensureIncomingCount = (url) => {
    if (!incomingCounts.has(url)) {
      incomingCounts.set(url, 0);
    }
  };

  const addInternalEdge = (sourceUrl, targetUrl) => {
    const sourceTargets = ensureOutgoingSet(sourceUrl);
    if (!sourceTargets.has(targetUrl)) {
      sourceTargets.add(targetUrl);
      incomingCounts.set(targetUrl, (incomingCounts.get(targetUrl) || 0) + 1);
    }
    ensureIncomingCount(sourceUrl);
    ensureOutgoingSet(targetUrl);
  };

  const dequeueNext = () => {
    for (let depth = 0; depth < queueByDepth.length; depth += 1) {
      if (queueByDepth[depth].length > 0) {
        const url = queueByDepth[depth].shift();
        return { url, depth };
      }
    }

    return null;
  };

  while (true) {
    if (visitedUrls.size >= maxPages) {
      stopReason = 'crawl budget reached (max pages reached)';
      break;
    }

    if (Date.now() - crawlStartedAt >= maxScanTimeMs) {
      stopReason = 'crawl budget reached (max scan time reached)';
      break;
    }

    const next = dequeueNext();
    if (!next) {
      log(`[crawlLinks] queue empty | total_pages_crawled=${visitedUrls.size}`);
      break;
    }

    const { url, depth } = next;
    queued.delete(url);
    const queueLengthAfterDequeue = queueByDepth.reduce((sum, bucket) => sum + bucket.length, 0);
    log(`[crawlLinks] dequeue | url=${url} | depth=${depth} | queue_size=${queueLengthAfterDequeue}`);

    if (visitedUrls.has(url)) {
      continue;
    }

    if (depth > maxDepth) {
      stopReason = 'crawl budget reached (max depth reached)';
      break;
    }

    let links = [];
    let success = false;

    for (let attempt = 1; attempt <= maxRetries; attempt += 1) {
      try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: pageTimeoutMs });

        links = await page.$$eval('a[href]', (as) =>
          as
            .map((a) => a.getAttribute('href'))
            .filter(Boolean)
            .map((href) => href.trim())
            .filter((href) => !href.startsWith('#'))
            .filter((href) => !href.startsWith('mailto:'))
            .filter((href) => !href.startsWith('tel:'))
            .filter((href) => !href.startsWith('javascript:'))
        );

        success = true;
        break;
      } catch {
        if (attempt < maxRetries) {
          await sleep(retryDelayMs);
        }
      }
    }

    if (!success) {
      continue;
    }

    visitedUrls.add(url);
    ensureIncomingCount(url);
    ensureOutgoingSet(url);

    for (const link of links) {
      let absolute;

      try {
        absolute = new URL(link, url).toString();
      } catch {
        continue;
      }

      absolute = normalizeUrl(absolute);
      if (!absolute) {
        continue;
      }

      let parsed;
      try {
        parsed = new URL(absolute);
      } catch {
        continue;
      }

      if (parsed.origin !== origin) {
        log(`[crawlLinks] url skipped (external) | source=${url} | candidate=${absolute}`);
        continue;
      }

      log(`[crawlLinks] url discovered | source=${url} | candidate=${absolute} | depth=${depth + 1}`);
      discoveredUrls.add(absolute);
      addInternalEdge(url, absolute);

      if (!depthByUrl.has(absolute) || depth + 1 < depthByUrl.get(absolute)) {
        depthByUrl.set(absolute, depth + 1);
      }
    }

    if (depth >= maxDepth || visitedUrls.size >= maxPages) {
      continue;
    }

    const remainingPageBudget = maxPages - visitedUrls.size;
    const globalQueueSize = queueByDepth.reduce((sum, bucket) => sum + bucket.length, 0);
    const queueCapacityLeft = Math.max(maxQueueSize - globalQueueSize, 0);
    const enqueueLimit = Math.min(maxLinksPerPage, remainingPageBudget, queueCapacityLeft);

    if (enqueueLimit <= 0) {
      if (queueCapacityLeft <= 0) {
        stopReason = 'crawl queue limit reached';
      }
      continue;
    }

    let added = 0;

    for (const absolute of outgoingLinks.get(url)) {
      if (Date.now() - crawlStartedAt >= maxScanTimeMs) {
        stopReason = 'crawl budget reached (max scan time reached)';
        break;
      }

      if (added >= enqueueLimit) {
        break;
      }

      if (shouldBlockLoopByPathPattern(absolute, loopPatternState, loopLimits)) {
        continue;
      }

      if (!visitedUrls.has(absolute) && !queued.has(absolute)) {
        const nextDepth = depth + 1;
        if (nextDepth <= maxDepth) {
          queueByDepth[nextDepth].push(absolute);
          queued.add(absolute);
          discoveredUrls.add(absolute);
          if (!depthByUrl.has(absolute) || nextDepth < depthByUrl.get(absolute)) {
            depthByUrl.set(absolute, nextDepth);
          }
          added += 1;
          const queueLengthAfterAdd = queueByDepth.reduce((sum, bucket) => sum + bucket.length, 0);
          log(`[crawlLinks] url added to crawl queue | url=${absolute} | depth=${nextDepth} | queue_size=${queueLengthAfterAdd}`);
        }
      } else {
        log(`[crawlLinks] url skipped (duplicate) | url=${absolute} | already_visited=${visitedUrls.has(absolute)} | already_queued=${queued.has(absolute)}`);
      }
    }

    if (stopReason === 'crawl budget reached (max scan time reached)') {
      break;
    }
  }

  if (stopReason) {
    console.log(`[crawlLinks] ${stopReason}`);
    log(`[crawlLinks] stop reason | reason=${stopReason}`);
  }

  log(`[crawlLinks] total pages crawled | total_pages_crawled=${visitedUrls.size} | total_discovered=${discoveredUrls.size}`);

  const crawledUrls = Array.from(visitedUrls).slice(0, maxPages);

  if (!includeLinkGraph) {
    return crawledUrls;
  }

  const crawlSetUrls = Array.from(discoveredUrls);
  const pageDepth = {};
  const incomingLinksCount = {};
  const outgoingLinksCount = {};

  for (const url of crawlSetUrls) {
    pageDepth[url] = depthByUrl.has(url) ? depthByUrl.get(url) : null;
    incomingLinksCount[url] = incomingCounts.get(url) || 0;
    outgoingLinksCount[url] = outgoingLinks.has(url) ? outgoingLinks.get(url).size : 0;
  }

  const internalLinks = [];
  for (const [sourceUrl, targets] of outgoingLinks.entries()) {
    for (const targetUrl of targets) {
      internalLinks.push({ source_url: sourceUrl, target_url: targetUrl });
    }
  }

  const pages = crawlSetUrls.map((url) => ({
    url,
    depth: pageDepth[url],
    incoming_links: incomingLinksCount[url],
    outgoing_links: outgoingLinksCount[url],
  }));

  const orphanPages = crawlSetUrls.filter((url) => url !== normalizedStartUrl && (incomingLinksCount[url] || 0) === 0);

  return {
    urls: crawledUrls,
    internal_links: internalLinks,
    page_depth: pageDepth,
    incoming_links_count: incomingLinksCount,
    outgoing_links_count: outgoingLinksCount,
    pages,
    orphan_pages: orphanPages,
  };
};
