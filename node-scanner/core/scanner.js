const puppeteer = require('puppeteer');
const path = require('path');
const crypto = require('crypto');
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

function hashContent(content) {
  return crypto.createHash('sha256').update(content || '').digest('hex');
}

function isRedirectStatus(code) {
  return [301, 302, 307, 308].includes(Number(code));
}

async function fetchWithTimeout(url, options = {}, timeoutMs = 10000) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  try {
    return await fetch(url, { ...options, signal: controller.signal });
  } finally {
    clearTimeout(timeout);
  }
}

async function resolveLinkStatus(targetUrl, maxRedirects = 6) {
  const chain = [];
  let current = targetUrl;

  for (let index = 0; index < maxRedirects; index += 1) {
    let response;
    try {
      response = await fetchWithTimeout(current, { method: 'HEAD', redirect: 'manual' });

      if ([400, 403, 405].includes(response.status)) {
        response = await fetchWithTimeout(current, { method: 'GET', redirect: 'manual' });
      }
    } catch {
      return {
        status_code: null,
        redirect_target: null,
        redirect_chain: chain,
        redirect_chain_length: chain.length,
      };
    }

    const statusCode = Number(response.status);
    const location = response.headers.get('location');
    const resolvedNext = location ? normalizeUrl(location, current) : null;

    if (isRedirectStatus(statusCode) && resolvedNext) {
      chain.push({ url: current, status_code: statusCode, target: resolvedNext });
      current = resolvedNext;
      continue;
    }

    return {
      status_code: statusCode,
      redirect_target: chain.length > 0 ? current : null,
      redirect_chain: chain,
      redirect_chain_length: chain.length,
    };
  }

  return {
    status_code: null,
    redirect_target: current,
    redirect_chain: chain,
    redirect_chain_length: chain.length,
  };
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
  const crawledLinks = [];

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
        canonical_url: null,
        content_hash: null,
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

        const { links, canonicalUrl, cleanedContent } = await page.evaluate(() => {
          const anchors = Array.from(document.querySelectorAll('a[href]'));
          const linkData = anchors
            .map((anchor) => {
              const rawHref = anchor.getAttribute('href') || anchor.href || '';
              const href = rawHref.trim();

              if (
                !href
                || href.startsWith('#')
                || href.startsWith('mailto:')
                || href.startsWith('tel:')
                || href.startsWith('javascript:')
              ) {
                return null;
              }

              return {
                href,
                anchor_text: (anchor.textContent || '').trim().slice(0, 1000),
                nofollow: /nofollow/i.test(anchor.getAttribute('rel') || ''),
              };
            })
            .filter(Boolean);

          const canonicalElement = document.querySelector('link[rel="canonical"]');
          const canonicalHref = canonicalElement ? (canonicalElement.getAttribute('href') || '').trim() : null;

          const clone = document.documentElement.cloneNode(true);
          clone.querySelectorAll('script, style, noscript').forEach((node) => node.remove());

          return {
            links: linkData,
            canonicalUrl: canonicalHref || null,
            cleanedContent: clone.outerHTML || '',
          };
        });

        pageResult.canonical_url = normalizeUrl(canonicalUrl, url) || null;
        pageResult.content_hash = hashContent(cleanedContent);

        for (const linkData of links) {
          const rawLink = linkData.href;
          const nextDepth = depth + 1;
          const normalized = normalizeUrl(rawLink, url);
          if (!normalized) {
            continue;
          }

          let parsedLink;
          try {
            parsedLink = new URL(normalized);
          } catch {
            continue;
          }

          const linkType = normalizeHost(parsedLink.hostname) === startHost ? 'internal' : 'external';

          crawledLinks.push({
            source_url: url,
            target_url: normalized,
            link_type: linkType,
            anchor_text: linkData.anchor_text || null,
            nofollow: linkData.nofollow === true,
          });

          if (!linkGraph.has(url)) {
            linkGraph.set(url, new Set());
          }
          linkGraph.get(url).add(normalized);

          if (linkType === 'internal') {
            pushUrl(normalized, url, nextDepth);
          }
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

    const linkStatusCache = new Map();
    const enrichedLinks = [];

    for (const link of crawledLinks) {
      if (!linkStatusCache.has(link.target_url)) {
        // Resolve once per target to keep the analysis lightweight.
        // eslint-disable-next-line no-await-in-loop
        linkStatusCache.set(link.target_url, await resolveLinkStatus(link.target_url));
      }

      enrichedLinks.push({
        ...link,
        ...(linkStatusCache.get(link.target_url) || {
          status_code: null,
          redirect_target: null,
          redirect_chain_length: 0,
          redirect_chain: [],
        }),
      });
    }

    const incomingCounts = new Map();
    const outgoingCounts = new Map();

    for (const link of enrichedLinks) {
      if (link.link_type !== 'internal') {
        continue;
      }

      outgoingCounts.set(link.source_url, (outgoingCounts.get(link.source_url) || 0) + 1);
      incomingCounts.set(link.target_url, (incomingCounts.get(link.target_url) || 0) + 1);
    }

    const pages = pageResults.map((entry) => ({
      url: entry.url,
      status: entry.status,
      alt_count: entry.alt_count,
      heading_count: entry.heading_count,
      error: entry.error,
      depth: entry.depth,
      canonical_url: entry.canonical_url,
      content_hash: entry.content_hash,
      internal_links_in: incomingCounts.get(entry.url) || 0,
      internal_links_out: outgoingCounts.get(entry.url) || 0,
      outgoing_links: linkGraph.has(entry.url) ? linkGraph.get(entry.url).size : 0,
    }));

    const duplicateGroups = Object.entries(
      pages.reduce((acc, pageEntry) => {
        if (!pageEntry.content_hash) {
          return acc;
        }
        acc[pageEntry.content_hash] = acc[pageEntry.content_hash] || [];
        acc[pageEntry.content_hash].push(pageEntry.url);
        return acc;
      }, {})
    )
      .filter(([, urls]) => urls.length > 1)
      .map(([content_hash, urls]) => ({ content_hash, urls }));

    const orphanPages = pages
      .filter((pageEntry) => Number(pageEntry.internal_links_in || 0) === 0 && Number(pageEntry.depth || 0) > 0)
      .map((pageEntry) => pageEntry.url);

    const result = {
      url: startUrl,
      pages_crawled: scannedPages,
      link_graph_pages: pages,
      crawl_links: enrichedLinks,
      duplicate_content_groups: duplicateGroups,
      orphan_pages: orphanPages,
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
