const { URL } = require('url');

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function toPositiveInt(value, fallback) {
  const num = Number(value);
  return Number.isFinite(num) && num > 0 ? Math.floor(num) : fallback;
}

module.exports = async function crawlLinks(page, startUrl, options = {}) {
  const maxPages = toPositiveInt(options.max_pages ?? options.maxPages, 10);
  const maxDepth = toPositiveInt(options.max_depth ?? options.maxDepth, 2);
  const pageTimeoutSeconds = toPositiveInt(options.page_timeout ?? options.pageTimeout, 30);
  const maxRetries = toPositiveInt(options.max_retries ?? options.maxRetries, 3);
  const retryDelaySeconds = toPositiveInt(options.retry_delay ?? options.retryDelay, 10);

  const pageTimeoutMs = pageTimeoutSeconds * 1000;
  const retryDelayMs = retryDelaySeconds * 1000;

  const visited = new Set();
  const queue = [{ url: startUrl, depth: 0 }];
  const queued = new Set([startUrl]);

  let origin;
  try {
    origin = new URL(startUrl).origin;
  } catch {
    return [];
  }

  while (queue.length > 0 && visited.size < maxPages) {
    const { url, depth } = queue.shift();
    queued.delete(url);

    if (visited.has(url)) {
      continue;
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

    visited.add(url);

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

      try {
        const parsed = new URL(absolute);
        if (parsed.origin !== origin) {
          continue;
        }
      } catch {
        continue;
      }

      if (!visited.has(absolute) && !queued.has(absolute) && queue.length + visited.size < maxPages) {
        queue.push({ url: absolute, depth: depth + 1 });
        queued.add(absolute);
      }
    }
  }

  return Array.from(visited).slice(0, maxPages);
};
