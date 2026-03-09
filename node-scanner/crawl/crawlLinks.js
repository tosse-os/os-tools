const { URL } = require('url');
const { normalizeUrl } = require('../utils/urlUtils');

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
  const maxPages = toPositiveInt(options.max_pages ?? options.maxPages, 10);
  const maxDepth = toPositiveInt(options.max_depth ?? options.maxDepth, 2);
  const maxScanTimeMs = toPositiveMs(options.max_scan_time ?? options.maxScanTime, 300000);
  const pageTimeoutSeconds = toPositiveInt(options.page_timeout ?? options.pageTimeout, 30);
  const maxRetries = toPositiveInt(options.max_retries ?? options.maxRetries, 3);
  const retryDelaySeconds = toPositiveInt(options.retry_delay ?? options.retryDelay, 10);

  const pageTimeoutMs = pageTimeoutSeconds * 1000;
  const retryDelayMs = retryDelaySeconds * 1000;
  const maxQueueSize = Math.max(maxPages * 4, 100);
  const maxLinksPerPage = Math.max(Math.min(maxPages, 100), 20);
  const loopLimits = {
    maxPatternVariants: Math.max(Math.min(maxPages, 25), 8),
  };

  const normalizedStartUrl = normalizeUrl(startUrl);
  if (!normalizedStartUrl) {
    return [];
  }

  const visitedUrls = new Set();
  const queued = new Set([normalizedStartUrl]);
  const queueByDepth = Array.from({ length: maxDepth + 1 }, (_, depth) => depth === 0 ? [normalizedStartUrl] : []);
  const crawlStartedAt = Date.now();
  const loopPatternState = new Map();

  let stopReason = null;

  let origin;
  try {
    origin = new URL(normalizedStartUrl).origin;
  } catch {
    return [];
  }

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
      break;
    }

    const { url, depth } = next;
    queued.delete(url);

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

    for (const link of links) {
      if (Date.now() - crawlStartedAt >= maxScanTimeMs) {
        stopReason = 'crawl budget reached (max scan time reached)';
        break;
      }

      if (added >= enqueueLimit) {
        break;
      }

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
        continue;
      }

      if (shouldBlockLoopByPathPattern(absolute, loopPatternState, loopLimits)) {
        continue;
      }

      if (!visitedUrls.has(absolute) && !queued.has(absolute)) {
        const nextDepth = depth + 1;
        if (nextDepth <= maxDepth) {
          queueByDepth[nextDepth].push(absolute);
          queued.add(absolute);
          added += 1;
        }
      }
    }

    if (stopReason === 'crawl budget reached (max scan time reached)') {
      break;
    }
  }

  if (stopReason) {
    console.log(`[crawlLinks] ${stopReason}`);
  }

  return Array.from(visitedUrls).slice(0, maxPages);
};
