const { URL } = require('url');

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

function normalizeUrl(rawUrl) {
  let parsed;

  try {
    parsed = new URL(rawUrl);
  } catch {
    return null;
  }

  parsed.hash = '';

  for (const key of Array.from(parsed.searchParams.keys())) {
    const lowerKey = key.toLowerCase();
    if (lowerKey.startsWith('utm_') || lowerKey === 'gclid' || lowerKey === 'fbclid' || lowerKey === 'msclkid') {
      parsed.searchParams.delete(key);
    }
  }

  let normalizedPath = parsed.pathname;
  if (normalizedPath.length > 1 && normalizedPath.endsWith('/')) {
    normalizedPath = normalizedPath.slice(0, -1);
  }

  parsed.pathname = normalizedPath;
  parsed.search = parsed.searchParams.toString() ? `?${parsed.searchParams.toString()}` : '';

  return parsed.toString();
}

module.exports = async function crawlLinks(page, startUrl, options = {}) {
  const maxPages = toPositiveInt(options.max_pages ?? options.maxPages, 10);
  const maxDepth = toPositiveInt(options.max_depth ?? options.maxDepth, 2);
  const maxScanTimeMs = toPositiveMs(options.max_scan_time ?? options.maxScanTime, 60000);
  const pageTimeoutSeconds = toPositiveInt(options.page_timeout ?? options.pageTimeout, 30);
  const maxRetries = toPositiveInt(options.max_retries ?? options.maxRetries, 3);
  const retryDelaySeconds = toPositiveInt(options.retry_delay ?? options.retryDelay, 10);

  const pageTimeoutMs = pageTimeoutSeconds * 1000;
  const retryDelayMs = retryDelaySeconds * 1000;

  const normalizedStartUrl = normalizeUrl(startUrl);
  if (!normalizedStartUrl) {
    return [];
  }

  const visitedUrls = new Set();
  const queue = [{ url: normalizedStartUrl, depth: 0 }];
  const queued = new Set([normalizedStartUrl]);
  const crawlStartedAt = Date.now();

  let stopReason = null;

  let origin;
  try {
    origin = new URL(normalizedStartUrl).origin;
  } catch {
    return [];
  }

  while (queue.length > 0) {
    if (visitedUrls.size >= maxPages) {
      stopReason = 'max pages reached';
      break;
    }

    if (Date.now() - crawlStartedAt >= maxScanTimeMs) {
      stopReason = 'crawl budget reached (max scan time reached)';
      break;
    }

    const { url, depth } = queue.shift();
    queued.delete(url);

    if (visitedUrls.has(url)) {
      continue;
    }

    if (depth > maxDepth) {
      stopReason = 'max depth reached';
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

    if (depth >= maxDepth) {
      continue;
    }

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

      try {
        const parsed = new URL(absolute);
        if (parsed.origin !== origin) {
          continue;
        }
      } catch {
        continue;
      }

      if (!visitedUrls.has(absolute) && !queued.has(absolute)) {
        queue.push({ url: absolute, depth: depth + 1 });
        queued.add(absolute);
      }
    }
  }

  if (stopReason) {
    console.log(`[crawlLinks] ${stopReason}`);
  }

  return Array.from(visitedUrls).slice(0, maxPages);
};
