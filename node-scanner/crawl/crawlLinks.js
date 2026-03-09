const { URL } = require('url');
const { normalizeUrl } = require('../utils/urlUtils');

function createLogger(logger) {
  return typeof logger === 'function' ? logger : () => {};
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function toPositiveInt(value, fallback) {
  const num = Number(value);
  return Number.isFinite(num) && num > 0 ? Math.floor(num) : fallback;
}

function toPositiveMs(value, fallbackMs) {
  const num = Number(value);
  return Number.isFinite(num) && num > 0 ? num : fallbackMs;
}

function getUrlIdentity(urlString) {
  try {
    const parsed = new URL(urlString);
    const identity = `${parsed.protocol}//${parsed.host}${parsed.pathname}${parsed.search}`;
    return identity.toLowerCase();
  } catch {
    return null;
  }
}

function normalizeHost(hostname) {
  return (hostname || '').toLowerCase().replace(/^www\./, '');
}

module.exports = async function crawlLinks(page, startUrl, options = {}) {
  const log = createLogger(options.logger);
  const maxPages = toPositiveInt(options.max_pages ?? options.maxPages, 10);
  const maxDepth = toPositiveInt(options.max_depth ?? options.maxDepth, 2);
  const maxScanTimeSeconds = toPositiveInt(options.max_scan_time ?? options.maxScanTime, 300);
  const pageTimeoutSeconds = toPositiveInt(options.page_timeout ?? options.pageTimeout, 30);
  const maxRetries = toPositiveInt(options.max_retries ?? options.maxRetries, 3);
  const retryDelaySeconds = toPositiveInt(options.retry_delay ?? options.retryDelay, 10);
  const includeLinkGraph = options.include_link_graph === true || options.includeLinkGraph === true;

  const pageTimeoutMs = pageTimeoutSeconds * 1000;
  const retryDelayMs = retryDelaySeconds * 1000;
  const maxScanTimeMs = maxScanTimeSeconds * 1000;

  const normalizedStartUrl = normalizeUrl(startUrl);

  log(
    `[crawlLinks] scan started | start_url=${startUrl} | normalized_start_url=${normalizedStartUrl || 'invalid'} | max_pages=${maxPages} | max_depth=${maxDepth} | max_scan_time_ms=${maxScanTimeMs}`
  );
  console.log('[CRAWLER] initialized', { startUrl: normalizedStartUrl || startUrl });

  if (!normalizedStartUrl) {
    return includeLinkGraph
      ? {
          urls: [],
          internal_links: [],
          page_depth: {},
          incoming_links_count: {},
          outgoing_links_count: {},
          pages: [],
          orphan_pages: [],
        }
      : [];
  }

  let startParsed;
  try {
    startParsed = new URL(normalizedStartUrl);
  } catch {
    return includeLinkGraph
      ? {
          urls: [],
          internal_links: [],
          page_depth: {},
          incoming_links_count: {},
          outgoing_links_count: {},
          pages: [],
          orphan_pages: [],
        }
      : [];
  }

  const startHost = normalizeHost(startParsed.hostname);

  const visitedUrls = new Set();
  const visitedIdentities = new Set();
  const queuedIdentities = new Set();
  const discoveredUrls = new Set([normalizedStartUrl]);
  const depthByUrl = new Map([[normalizedStartUrl, 0]]);
  const queue = [{ url: normalizedStartUrl, depth: 0 }];
  queuedIdentities.add(getUrlIdentity(normalizedStartUrl) || normalizedStartUrl.toLowerCase());

  const outgoingLinks = new Map();
  const incomingCounts = new Map();

  const crawlStartedAt = Date.now();
  let stopReason = null;

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

  while (queue.length > 0) {
    if (visitedUrls.size >= maxPages) {
      stopReason = 'crawl budget reached (max pages reached)';
      break;
    }

    if (Date.now() - crawlStartedAt >= maxScanTimeMs) {
      stopReason = 'crawl budget reached (max scan time reached)';
      break;
    }

    const next = queue.shift();

    if (!next) {
      break;
    }

    const { url, depth } = next;
    const currentIdentity = getUrlIdentity(url) || url.toLowerCase();
    queuedIdentities.delete(currentIdentity);

    log(`[crawlLinks] current crawl depth | url=${url} | depth=${depth}`);
    log(`[crawlLinks] current queue size | size=${queue.length}`);
    log(`[crawlLinks] url crawled | url=${url} | depth=${depth}`);
    console.log('[CRAWLER] visiting', url);

    if (visitedIdentities.has(currentIdentity)) {
      log(`[crawlLinks] links rejected (duplicate) | url=${url} | reason=already_visited`);
      console.log('[CRAWLER] skip link', { reason: 'already_visited', url });
      continue;
    }

    if (depth > maxDepth) {
      log(`[crawlLinks] links rejected | url=${url} | reason=max_depth_exceeded | depth=${depth}`);
      console.log('[CRAWLER] skip link', { reason: 'max_depth_exceeded', url });
      continue;
    }

    let links = [];
    let success = false;

    for (let attempt = 1; attempt <= maxRetries; attempt += 1) {
      try {
        try {
          await page.goto(url, { waitUntil: 'domcontentloaded', timeout: pageTimeoutMs });
          console.log('[CRAWLER] page loaded', url);
        } catch (err) {
          console.error('[CRAWLER] navigation error', err);
          throw err;
        }

        links = await page.$$eval('a[href]', (anchors) =>
          anchors
            .map((anchor) => anchor.href || anchor.getAttribute('href'))
            .filter(Boolean)
            .map((href) => href.trim())
            .filter((href) => !href.startsWith('#'))
            .filter((href) => !href.startsWith('mailto:'))
            .filter((href) => !href.startsWith('tel:'))
            .filter((href) => !href.startsWith('javascript:'))
        );

        console.log('[CRAWLER] links extracted', {
          url,
          count: links.length,
        });

        success = true;
        break;
      } catch (error) {
        log(
          `[crawlLinks] links rejected | reason=page_load_failed | url=${url} | attempt=${attempt} | message=${error.message}`
        );
        console.log('[CRAWLER] skip link', { reason: 'page_load_failed', url });
        if (attempt < maxRetries) {
          await sleep(retryDelayMs);
        }
      }
    }

    if (!success) {
      continue;
    }

    visitedUrls.add(url);
    visitedIdentities.add(currentIdentity);
    ensureIncomingCount(url);
    ensureOutgoingSet(url);

    const nextDepth = depth + 1;
    log(`[crawlLinks] links discovered | source=${url} | count=${links.length} | next_depth=${nextDepth}`);

    for (const rawLink of links) {
      const normalized = normalizeUrl(rawLink, url);

      if (!normalized) {
        log(`[crawlLinks] links rejected | reason=normalization_failed | source=${url} | candidate=${rawLink}`);
        console.log('[CRAWLER] skip link', { reason: 'normalization_failed', url: rawLink });
        continue;
      }

      let parsed;
      try {
        parsed = new URL(normalized);
      } catch {
        log(`[crawlLinks] links rejected | reason=parse_failed | source=${url} | candidate=${normalized}`);
        console.log('[CRAWLER] skip link', { reason: 'parse_failed', url: normalized });
        continue;
      }

      const isInternal = normalizeHost(parsed.hostname) === startHost;
      if (!isInternal) {
        log(`[crawlLinks] links rejected (external) | source=${url} | link=${normalized}`);
        console.log('[CRAWLER] skip link', { reason: 'external', url: normalized });
        continue;
      }

      discoveredUrls.add(normalized);
      addInternalEdge(url, normalized);

      if (!depthByUrl.has(normalized) || nextDepth < depthByUrl.get(normalized)) {
        depthByUrl.set(normalized, nextDepth);
      }

      if (nextDepth > maxDepth) {
        log(`[crawlLinks] links rejected | reason=max_depth_reached | link=${normalized} | next_depth=${nextDepth}`);
        console.log('[CRAWLER] skip link', { reason: 'max_depth_reached', url: normalized });
        continue;
      }

      if (visitedUrls.size + queue.length >= maxPages) {
        log(`[crawlLinks] links rejected | reason=max_pages_budget | link=${normalized}`);
        console.log('[CRAWLER] skip link', { reason: 'max_pages_budget', url: normalized });
        continue;
      }

      if (Date.now() - crawlStartedAt >= maxScanTimeMs) {
        stopReason = 'crawl budget reached (max scan time reached)';
        log(`[crawlLinks] links rejected | reason=max_scan_time_reached | link=${normalized}`);
        console.log('[CRAWLER] skip link', { reason: 'max_scan_time_reached', url: normalized });
        break;
      }

      const identity = getUrlIdentity(normalized) || normalized.toLowerCase();

      if (visitedIdentities.has(identity) || queuedIdentities.has(identity)) {
        log(`[crawlLinks] links rejected (duplicate) | link=${normalized} | duplicate_key=${identity}`);
        console.log('[CRAWLER] skip link', { reason: 'duplicate', url: normalized });
        continue;
      }

      queue.push({ url: normalized, depth: nextDepth });
      queuedIdentities.add(identity);
      log(`[crawlLinks] links accepted into crawl queue | url=${normalized} | depth=${nextDepth} | queue_size=${queue.length}`);
      console.log('[CRAWLER] queue push', normalized);
    }

    if (stopReason === 'crawl budget reached (max scan time reached)') {
      break;
    }
  }

  if (!stopReason) {
    if (queue.length === 0) {
      stopReason = 'crawl queue exhausted';
    } else if (visitedUrls.size >= maxPages) {
      stopReason = 'crawl budget reached (max pages reached)';
    } else if (Date.now() - crawlStartedAt >= maxScanTimeMs) {
      stopReason = 'crawl budget reached (max scan time reached)';
    }
  }

  log(`[crawlLinks] scan stop | reason=${stopReason || 'completed'} | pages_crawled=${visitedUrls.size} | urls_discovered=${discoveredUrls.size}`);
  console.log('[CRAWLER] finished', {
    pages_crawled: visitedUrls.size,
  });

  const crawledUrls = Array.from(visitedUrls).slice(0, maxPages);

  if (!includeLinkGraph) {
    return crawledUrls;
  }

  const crawlSetUrls = Array.from(discoveredUrls);
  const pageDepth = {};
  const incomingLinksCount = {};
  const outgoingLinksCount = {};

  for (const discoveredUrl of crawlSetUrls) {
    pageDepth[discoveredUrl] = depthByUrl.has(discoveredUrl) ? depthByUrl.get(discoveredUrl) : null;
    incomingLinksCount[discoveredUrl] = incomingCounts.get(discoveredUrl) || 0;
    outgoingLinksCount[discoveredUrl] = outgoingLinks.has(discoveredUrl) ? outgoingLinks.get(discoveredUrl).size : 0;
  }

  const internalLinks = [];
  for (const [sourceUrl, targets] of outgoingLinks.entries()) {
    for (const targetUrl of targets) {
      internalLinks.push({ source_url: sourceUrl, target_url: targetUrl });
    }
  }

  const pages = crawlSetUrls.map((discoveredUrl) => ({
    url: discoveredUrl,
    depth: pageDepth[discoveredUrl],
    incoming_links: incomingLinksCount[discoveredUrl],
    outgoing_links: outgoingLinksCount[discoveredUrl],
  }));

  const orphanPages = crawlSetUrls.filter(
    (discoveredUrl) => discoveredUrl !== normalizedStartUrl && (incomingLinksCount[discoveredUrl] || 0) === 0
  );

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
