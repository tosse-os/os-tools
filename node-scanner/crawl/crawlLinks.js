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

  const normalizedStartUrl = normalizeUrl(startUrl);

  log(`[crawlLinks] scan start | start_url=${startUrl} | normalized_start_url=${normalizedStartUrl || 'invalid'} | max_pages=${maxPages} | max_depth=${maxDepth} | max_scan_time_ms=${maxScanTimeMs}`);

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

  let origin;
  try {
    origin = new URL(normalizedStartUrl).origin;
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

  const visitedUrls = new Set();
  const visitedIdentities = new Set();
  const queuedIdentities = new Set();
  const discoveredUrls = new Set([normalizedStartUrl]);
  const depthByUrl = new Map([[normalizedStartUrl, 0]]);
  const queue = [{ url: normalizedStartUrl, depth: 0 }];

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

    log(`[crawlLinks] url crawled | url=${url} | current_depth=${depth} | queue_size=${queue.length}`);

    if (visitedIdentities.has(currentIdentity)) {
      log(`[crawlLinks] links skipped | reason=already_visited | url=${url} | current_depth=${depth}`);
      continue;
    }

    if (depth > maxDepth) {
      log(`[crawlLinks] links skipped | reason=max_depth_exceeded | url=${url} | current_depth=${depth}`);
      continue;
    }

    let links = [];
    let success = false;

    for (let attempt = 1; attempt <= maxRetries; attempt += 1) {
      try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: pageTimeoutMs });

        links = await page.$$eval('a[href]', (anchors) =>
          anchors
            .map((anchor) => anchor.getAttribute('href'))
            .filter(Boolean)
            .map((href) => href.trim())
            .filter((href) => !href.startsWith('#'))
            .filter((href) => !href.startsWith('mailto:'))
            .filter((href) => !href.startsWith('tel:'))
            .filter((href) => !href.startsWith('javascript:'))
        );

        success = true;
        break;
      } catch (error) {
        log(`[crawlLinks] links skipped | reason=page_load_failed | url=${url} | attempt=${attempt} | message=${error.message}`);
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

    for (const rawLink of links) {
      let absolute;

      try {
        absolute = new URL(rawLink, url).toString();
      } catch {
        log(`[crawlLinks] links skipped | reason=invalid_url | source=${url} | candidate=${rawLink} | current_depth=${depth}`);
        continue;
      }

      const normalized = normalizeUrl(absolute);
      if (!normalized) {
        log(`[crawlLinks] links skipped | reason=normalization_failed | source=${url} | candidate=${absolute} | current_depth=${depth}`);
        continue;
      }

      let parsed;
      try {
        parsed = new URL(normalized);
      } catch {
        log(`[crawlLinks] links skipped | reason=parse_failed | source=${url} | candidate=${normalized} | current_depth=${depth}`);
        continue;
      }

      if (parsed.origin !== origin) {
        log(`[crawlLinks] links skipped | reason=external_link | source=${url} | candidate=${normalized} | current_depth=${depth}`);
        continue;
      }

      log(`[crawlLinks] links discovered | source=${url} | link=${normalized} | discovered_depth=${nextDepth}`);

      discoveredUrls.add(normalized);
      addInternalEdge(url, normalized);

      if (!depthByUrl.has(normalized) || nextDepth < depthByUrl.get(normalized)) {
        depthByUrl.set(normalized, nextDepth);
      }

      if (nextDepth > maxDepth) {
        log(`[crawlLinks] links skipped | reason=max_depth_reached | link=${normalized} | current_depth=${depth} | next_depth=${nextDepth}`);
        continue;
      }

      if (visitedUrls.size + queue.length >= maxPages) {
        log(`[crawlLinks] links skipped | reason=max_pages_budget | link=${normalized} | current_depth=${depth}`);
        continue;
      }

      if (Date.now() - crawlStartedAt >= maxScanTimeMs) {
        stopReason = 'crawl budget reached (max scan time reached)';
        log(`[crawlLinks] links skipped | reason=max_scan_time_reached | link=${normalized} | current_depth=${depth}`);
        break;
      }

      const identity = getUrlIdentity(normalized) || normalized.toLowerCase();

      if (visitedIdentities.has(identity) || queuedIdentities.has(identity)) {
        log(`[crawlLinks] links skipped | reason=duplicate | link=${normalized} | duplicate_key=${identity}`);
        continue;
      }

      queue.push({ url: normalized, depth: nextDepth });
      queuedIdentities.add(identity);
      log(`[crawlLinks] links queued | url=${normalized} | depth=${nextDepth} | queue_size=${queue.length}`);
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
