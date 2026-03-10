const puppeteer = require('puppeteer');
const path = require('path');
const { URL } = require('url');
const altCheck = require('../checks/altCheck');
const headingCheck = require('../checks/headingCheck');
const statusCheck = require('../checks/statusCheck');
const { createStructuredLogger } = require('../utils/structuredLogger');
const { normalizeUrl } = require('../utils/urlUtils');

const logFile = path.resolve(__dirname, '..', '..', 'storage', 'logs', 'node-scanner.log');
const logger = createStructuredLogger({
  logFilePath: logFile,
  output: process.stderr,
});

function toPositiveInt(value, fallback) {
  const num = Number(value);
  return Number.isFinite(num) && num > 0 ? Math.floor(num) : fallback;
}

function normalizeHost(hostname) {
  return (hostname || '').toLowerCase().replace(/^www\./, '');
}

function emit(event) {
  process.stdout.write(`${JSON.stringify(event)}\n`);
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

let options;
try {
  options = JSON.parse(process.argv[2]);
} catch (err) {
  logger.error('scan_error', { error: 'Ungültige Optionen', details: err.message });
  emit({ type: 'scan_finished', status: 'failed', error: err.message });
  process.exit(1);
}

(async () => {
  const scanId = options.scan_id || options.scanId || 'unknown';
  const startUrl = normalizeUrl(options.url) || options.url;
  const maxPages = toPositiveInt(options.max_pages ?? options.maxPages, 20);
  const maxDepth = toPositiveInt(options.max_depth ?? options.maxDepth, 2);
  const pageTimeout = toPositiveInt(options.page_timeout ?? options.pageTimeout, 30);
  const maxRetries = Math.min(2, toPositiveInt(options.max_retries ?? options.maxRetries, 2));
  const retryDelaySeconds = toPositiveInt(options.retry_delay ?? options.retryDelay, 2);
  const concurrency = toPositiveInt(options.concurrency ?? process.env.CRAWLER_CONCURRENCY, 6);
  const checks = Array.isArray(options.checks) ? options.checks : [];

  const scanLogger = logger.child({ scan_id: scanId });

  const queue = [{ url: startUrl, depth: 0 }];
  const visited_urls = new Set();
  const queued_urls = new Set([startUrl]);

  const linkGraph = new Map();
  const pageDepth = new Map([[startUrl, 0]]);
  const pageResults = [];

  let scannedPages = 0;
  let finished = false;

  let startHost;
  try {
    startHost = normalizeHost(new URL(startUrl).hostname);
  } catch {
    emit({ type: 'scan_finished', status: 'failed', error: 'Invalid start url' });
    process.exit(1);
  }

  function pushUrl(rawUrl, parentUrl, depth) {
    const normalized = normalizeUrl(rawUrl, parentUrl);
    if (!normalized) {
      return;
    }

    let parsed;
    try {
      parsed = new URL(normalized);
    } catch {
      return;
    }

    if (normalizeHost(parsed.hostname) !== startHost) {
      return;
    }

    if (depth > maxDepth) {
      return;
    }

    if (visited_urls.has(normalized) || queued_urls.has(normalized)) {
      return;
    }

    queue.push({ url: normalized, depth });
    queued_urls.add(normalized);

    if (!pageDepth.has(normalized) || depth < pageDepth.get(normalized)) {
      pageDepth.set(normalized, depth);
    }
  }

  async function createWorker(workerId) {
    let browser = await puppeteer.launch({ headless: 'new' });
    let page = await browser.newPage();
    let pagesSinceRestart = 0;

    while (!finished) {
      const item = queue.shift();
      if (!item) {
        if (visited_urls.size >= maxPages) {
          break;
        }

        if (queue.length === 0) {
          await sleep(150);
          if (queue.length === 0) {
            break;
          }
        }
        continue;
      }

      const { url, depth } = item;
      queued_urls.delete(url);

      if (visited_urls.has(url) || scannedPages >= maxPages) {
        continue;
      }

      visited_urls.add(url);
      scannedPages += 1;

      if (!linkGraph.has(url)) {
        linkGraph.set(url, new Set());
      }

      emit({
        type: 'crawl_progress',
        status: 'running',
        scanned_pages: scannedPages,
        queue_size: queue.length,
        current_url: url,
        total: maxPages,
      });

      let pageResult = {
        type: 'page_scanned',
        url,
        status: null,
        alt_count: 0,
        heading_count: 0,
        error: null,
      };

      try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: pageTimeout * 1000 });

        if (checks.includes('status')) {
          const statusResult = await statusCheck(page, url);
          pageResult.status = statusResult?.status ? String(statusResult.status) : null;
        }

        if (checks.includes('alt')) {
          const altResult = await altCheck(page);
          pageResult.alt_count = Array.isArray(altResult?.missingAlt)
            ? altResult.missingAlt.length
            : Number(altResult?.count ?? 0);
        }

        if (checks.includes('heading')) {
          const headingResult = await headingCheck(page);
          pageResult.heading_count = Array.isArray(headingResult?.headings)
            ? headingResult.headings.length
            : Number(headingResult?.count ?? 0);
        }

        const links = await page.$$eval('a[href]', (anchors) =>
          anchors
            .map((anchor) => anchor.getAttribute('href') || anchor.href)
            .filter(Boolean)
            .map((href) => href.trim())
            .filter((href) => !href.startsWith('#'))
            .filter((href) => !href.startsWith('mailto:'))
            .filter((href) => !href.startsWith('tel:'))
            .filter((href) => !href.startsWith('javascript:'))
        );

        for (const rawLink of links) {
          const nextDepth = depth + 1;
          const normalized = normalizeUrl(rawLink, url);
          if (!normalized) {
            continue;
          }

          if (!linkGraph.has(url)) {
            linkGraph.set(url, new Set());
          }
          linkGraph.get(url).add(normalized);
          pushUrl(normalized, url, nextDepth);
        }
      } catch (error) {
        let retryError = error;
        let success = false;

        for (let attempt = 1; attempt <= maxRetries; attempt += 1) {
          try {
            await page.goto(url, { waitUntil: 'domcontentloaded', timeout: pageTimeout * 1000 });
            success = true;
            retryError = null;
            break;
          } catch (retryAttemptError) {
            retryError = retryAttemptError;
            if (attempt < maxRetries) {
              await sleep(retryDelaySeconds * 1000);
            }
          }
        }

        if (!success && retryError) {
          pageResult.error = retryError.message;
          pageResult.status = 'error';
        }
      }

      pageResults.push({ ...pageResult, depth: pageDepth.get(url) ?? depth });
      emit(pageResult);

      pagesSinceRestart += 1;
      if (pagesSinceRestart >= 100) {
        await page.close();
        await browser.close();
        browser = await puppeteer.launch({ headless: 'new' });
        page = await browser.newPage();
        pagesSinceRestart = 0;
      }

      if (scannedPages >= maxPages) {
        finished = true;
      }
    }

    await page.close();
    await browser.close();
    scanLogger.info('worker_finished', { worker_id: workerId, scanned_pages: scannedPages });
  }

  try {
    scanLogger.info('scan_started', {
      url: startUrl,
      max_pages: maxPages,
      max_depth: maxDepth,
      page_timeout: pageTimeout,
      max_retries: maxRetries,
      concurrency,
    });

    const workers = Array.from({ length: concurrency }, (_, index) => createWorker(index + 1));
    await Promise.all(workers);

    const pages = pageResults.map((entry) => ({
      url: entry.url,
      status: entry.status,
      alt_count: entry.alt_count,
      heading_count: entry.heading_count,
      error: entry.error,
      depth: entry.depth,
      outgoing_links: linkGraph.has(entry.url) ? linkGraph.get(entry.url).size : 0,
    }));

    const result = {
      url: startUrl,
      pages_crawled: scannedPages,
      link_graph_pages: pages,
    };

    emit({
      type: 'scan_finished',
      status: 'done',
      scanned_pages: scannedPages,
      queue_size: queue.length,
      current_url: null,
      total: maxPages,
    });

    emit({ type: 'scan_result', result });
  } catch (error) {
    emit({
      type: 'scan_finished',
      status: 'failed',
      scanned_pages: scannedPages,
      queue_size: queue.length,
      current_url: null,
      total: maxPages,
      error: error.message,
    });
    process.exit(1);
  }
})();
