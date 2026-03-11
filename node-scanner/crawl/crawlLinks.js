const { URL } = require('url');
const crypto = require('crypto');
const { normalizeUrl } = require('../utils/urlUtils');

function createLogger(logger) {
  if (logger && typeof logger.info === 'function') {
    return logger;
  }

  if (typeof logger === 'function') {
    return {
      error: logger,
      warn: logger,
      info: logger,
      debug: logger,
    };
  }

  return {
    error: () => {},
    warn: () => {},
    info: () => {},
    debug: () => {},
  };
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function toPositiveInt(value, fallback) {
  const num = Number(value);
  return Number.isFinite(num) && num > 0 ? Math.floor(num) : fallback;
}


function normalizeHost(hostname) {
  return (hostname || '').toLowerCase().replace(/^www\./, '');
}

function hasSkippedPath(pathname) {
  if (!pathname) {
    return false;
  }

  const normalizedPath = pathname.toLowerCase();
  const skippedPrefixes = ['/comment/', '/user/', '/admin/', '/node/'];

  return skippedPrefixes.some((prefix) => normalizedPath.startsWith(prefix));
}

module.exports = async function crawlLinks(page, startUrl, options = {}) {
  const log = createLogger(options.logger);
  const maxPages = Math.max(2, toPositiveInt(options.max_pages ?? options.maxPages, 10));
  const maxDepth = toPositiveInt(options.max_depth ?? options.maxDepth, 2);
  const maxScanTimeSeconds = toPositiveInt(options.max_scan_time ?? options.maxScanTime, 300);
  const maxRetries = toPositiveInt(options.max_retries ?? options.maxRetries, 3);
  const retryDelaySeconds = toPositiveInt(options.retry_delay ?? options.retryDelay, 10);
  const includeLinkGraph = options.include_link_graph === true || options.includeLinkGraph === true;
  const onProgress = typeof options.onProgress === 'function' ? options.onProgress : null;

  const retryDelayMs = retryDelaySeconds * 1000;
  const maxScanTimeMs = maxScanTimeSeconds * 1000;

  const normalizedStartUrl = normalizeUrl(startUrl) || startUrl;
  const scanId = options.scan_id ?? options.scanId ?? 'unknown';

  log.info('crawl_initialized', {
    scan_id: scanId,
    startUrl: normalizedStartUrl || startUrl,
    max_pages: maxPages,
    max_depth: maxDepth,
    max_scan_time_ms: maxScanTimeMs,
  });

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
  const queuedUrls = new Set();
  const discoveredUrls = new Set([normalizedStartUrl]);
  const depthByUrl = new Map([[normalizedStartUrl, 0]]);
  const queue = [{ url: normalizedStartUrl, depth: 0 }];
  queuedUrls.add(normalizedStartUrl);

  const outgoingLinks = new Map();
  const incomingCounts = new Map();
  const pageSeoMetrics = new Map();

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
    queuedUrls.delete(url);

    const skippedCounts = {};
    const incrementSkipped = (reason) => {
      skippedCounts[reason] = (skippedCounts[reason] || 0) + 1;
    };

    log.info('crawl_progress', {
      scan_id: scanId,
      scanned_pages: visitedUrls.size,
      queue_size: queue.length,
      current_url: url,
      current_depth: depth,
    });

    if (onProgress) {
      onProgress({
        scanned_pages: visitedUrls.size,
        queue_size: queue.length,
        current_url: url,
      });
    }

    if (visitedUrls.has(url)) {
      incrementSkipped('already_visited');
      log.debug('crawl_link_skipped_summary', { scan_id: scanId, page_url: url, skipped_total: 1, skipped_reasons: skippedCounts });
      continue;
    }

    if (depth > maxDepth) {
      incrementSkipped('max_depth_exceeded');
      log.debug('crawl_link_skipped_summary', { scan_id: scanId, page_url: url, skipped_total: 1, skipped_reasons: skippedCounts });
      continue;
    }

    try {
      const currentParsed = new URL(url);
      if (hasSkippedPath(currentParsed.pathname)) {
        incrementSkipped('excluded_path');
        log.debug('crawl_link_skipped_summary', { scan_id: scanId, page_url: url, skipped_total: 1, skipped_reasons: skippedCounts });
        continue;
      }
    } catch {
      incrementSkipped('parse_failed');
      log.debug('crawl_link_skipped_summary', { scan_id: scanId, page_url: url, skipped_total: 1, skipped_reasons: skippedCounts });
      continue;
    }

    let links = [];
    let success = false;

    for (let attempt = 1; attempt <= maxRetries; attempt += 1) {
      try {
        const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
        const statusCode = response ? response.status() : null;

        const pageData = await page.evaluate((crawlStartHost) => {
          const normalizeBrowserHost = (hostname) => (hostname || '').toLowerCase().replace(/^www\./, '');
          const isSkippableHref = (href) => !href
            || href.startsWith('#')
            || href.startsWith('mailto:')
            || href.startsWith('tel:')
            || href.startsWith('javascript:');

          const internalLinks = [];
          let externalLinksCount = 0;

          document.querySelectorAll('a[href]').forEach((anchor) => {
            const rawHref = (anchor.getAttribute('href') || anchor.href || '').trim();
            if (isSkippableHref(rawHref)) {
              return;
            }

            let parsed;
            try {
              parsed = new URL(rawHref, document.baseURI);
            } catch {
              return;
            }

            if (normalizeBrowserHost(parsed.hostname) === crawlStartHost) {
              internalLinks.push(parsed.href);
            } else {
              externalLinksCount += 1;
            }
          });

          const title = document.title || null;
          const metaDescription = document.querySelector('meta[name="description"]')?.getAttribute('content') || null;
          const canonical = document.querySelector('link[rel="canonical"]')?.getAttribute('href') || null;
          const images = Array.from(document.querySelectorAll('img'));
          const imagesMissingAlt = images.filter((img) => (img.alt || '').trim() === '').length;

          return {
            title,
            meta_description: metaDescription,
            canonical,
            h1_count: document.querySelectorAll('h1').length,
            h2_count: document.querySelectorAll('h2').length,
            h3_count: document.querySelectorAll('h3').length,
            image_count: images.length,
            images_missing_alt: imagesMissingAlt,
            internal_links: internalLinks,
            internal_links_count: internalLinks.length,
            external_links_count: externalLinksCount,
            text_content: document.body?.innerText || '',
          };
        }, startHost);

        const textHash = crypto.createHash('md5').update(pageData.text_content).digest('hex');
        links = pageData.internal_links;

        pageSeoMetrics.set(url, {
          url,
          depth,
          status_code: statusCode,
          title: pageData.title,
          meta_description: pageData.meta_description,
          canonical: pageData.canonical,
          h1_count: pageData.h1_count,
          h2_count: pageData.h2_count,
          h3_count: pageData.h3_count,
          image_count: pageData.image_count,
          images_missing_alt: pageData.images_missing_alt,
          internal_links_count: pageData.internal_links_count,
          external_links_count: pageData.external_links_count,
          text_hash: textHash,
        });

        success = true;
        break;
      } catch (error) {
        incrementSkipped('page_load_failed');
        log.warn('crawl_page_retry', {
          scan_id: scanId,
          url,
          attempt,
          max_retries: maxRetries,
          error: error.message,
        });
        if (attempt < maxRetries) {
          await sleep(retryDelayMs);
        }
      }
    }

    if (!success) {
      log.debug('crawl_link_skipped_summary', {
        scan_id: scanId,
        page_url: url,
        skipped_total: Object.values(skippedCounts).reduce((acc, count) => acc + count, 0),
        skipped_reasons: skippedCounts,
      });
      continue;
    }

    visitedUrls.add(url);
    ensureIncomingCount(url);
    ensureOutgoingSet(url);

    const nextDepth = depth + 1;
    for (const rawLink of links) {
      const normalized = normalizeUrl(rawLink, url);

      if (!normalized) {
        incrementSkipped('normalization_failed');
        continue;
      }

      let parsed;
      try {
        parsed = new URL(normalized);
      } catch {
        incrementSkipped('parse_failed');
        continue;
      }

      const isInternal = normalizeHost(parsed.hostname) === startHost;
      if (!isInternal) {
        incrementSkipped('external');
        continue;
      }

      if (hasSkippedPath(parsed.pathname)) {
        incrementSkipped('excluded_path');
        continue;
      }

      discoveredUrls.add(normalized);
      addInternalEdge(url, normalized);

      if (!depthByUrl.has(normalized) || nextDepth < depthByUrl.get(normalized)) {
        depthByUrl.set(normalized, nextDepth);
      }

      if (nextDepth > maxDepth) {
        incrementSkipped('max_depth_reached');
        continue;
      }

      if (visitedUrls.size >= maxPages) {
        incrementSkipped('max_pages_budget');
        continue;
      }

      if (Date.now() - crawlStartedAt >= maxScanTimeMs) {
        stopReason = 'crawl budget reached (max scan time reached)';
        incrementSkipped('max_scan_time_reached');
        break;
      }

      if (visitedUrls.has(normalized) || queuedUrls.has(normalized)) {
        incrementSkipped('duplicate');
        continue;
      }

      queue.push({ url: normalized, depth: nextDepth });
      queuedUrls.add(normalized);
    }

    log.info('page_crawled', {
      scan_id: scanId,
      url,
      depth,
      links_found: links.length,
      queue_size: queue.length,
      visited: visitedUrls.size,
    });

    const skippedTotal = Object.values(skippedCounts).reduce((acc, count) => acc + count, 0);
    if (skippedTotal > 0) {
      log.debug('crawl_link_skipped_summary', {
        scan_id: scanId,
        page_url: url,
        skipped_total: skippedTotal,
        skipped_reasons: skippedCounts,
      });
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

  log.info('crawl_finished', {
    scan_id: scanId,
    pages_crawled: visitedUrls.size,
    urls_discovered: discoveredUrls.size,
    stop_reason: stopReason || 'completed',
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

  const pages = crawlSetUrls.map((discoveredUrl) => {
    const seoMetrics = pageSeoMetrics.get(discoveredUrl);

    return {
      url: discoveredUrl,
      depth: pageDepth[discoveredUrl],
      incoming_links: incomingLinksCount[discoveredUrl],
      outgoing_links: outgoingLinksCount[discoveredUrl],
      status_code: seoMetrics ? seoMetrics.status_code : null,
      title: seoMetrics ? seoMetrics.title : null,
      meta_description: seoMetrics ? seoMetrics.meta_description : null,
      canonical: seoMetrics ? seoMetrics.canonical : null,
      h1_count: seoMetrics ? seoMetrics.h1_count : null,
      h2_count: seoMetrics ? seoMetrics.h2_count : null,
      h3_count: seoMetrics ? seoMetrics.h3_count : null,
      image_count: seoMetrics ? seoMetrics.image_count : null,
      images_missing_alt: seoMetrics ? seoMetrics.images_missing_alt : null,
      internal_links_count: seoMetrics ? seoMetrics.internal_links_count : null,
      external_links_count: seoMetrics ? seoMetrics.external_links_count : null,
      text_hash: seoMetrics ? seoMetrics.text_hash : null,
    };
  });

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
