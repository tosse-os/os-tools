const puppeteer = require('puppeteer');
const altCheck = require('../checks/altCheck');
const headingCheck = require('../checks/headingCheck');
const crawlLinks = require('../crawl/crawlLinks');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { createStructuredLogger } = require('../utils/structuredLogger');
const { normalizeUrl } = require('../utils/urlUtils');

const logFile = path.resolve(__dirname, '..', '..', 'storage', 'logs', 'node-scanner.log');
const rootLogger = createStructuredLogger({
  logFilePath: logFile,
  output: process.stderr,
});

function log(message) {
  rootLogger.info('scanner_message', { text: message });
}

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function toPositiveInt(value, fallback) {
  const num = Number(value);
  return Number.isFinite(num) && num > 0 ? Math.floor(num) : fallback;
}

function decodeHtmlEntities(text) {
  return String(text || '')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;|&apos;/gi, "'")
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>');
}

function stripTags(html) {
  return decodeHtmlEntities(String(html || '').replace(/<[^>]+>/g, ' '))
    .replace(/\s+/g, ' ')
    .trim();
}

function extractFromHtml(html) {
  const safeHtml = String(html || '');
  const titleMatch = safeHtml.match(/<title[^>]*>([\s\S]*?)<\/title>/i);

  const h1 = Array.from(safeHtml.matchAll(/<h1[^>]*>([\s\S]*?)<\/h1>/gi)).map(match => stripTags(match[1]));

  const meta = Array.from(safeHtml.matchAll(/<meta\s+[^>]*>/gi)).map(match => match[0]);

  const schema = Array.from(
    safeHtml.matchAll(/<script[^>]*type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi)
  ).map(match => match[1].trim());

  const links = Array.from(safeHtml.matchAll(/<a\s+[^>]*href=["']([^"']+)["'][^>]*>/gi)).map(match => match[1]);

  const content = stripTags(
    safeHtml
      .replace(/<script[\s\S]*?<\/script>/gi, ' ')
      .replace(/<style[\s\S]*?<\/style>/gi, ' ')
      .replace(/<noscript[\s\S]*?<\/noscript>/gi, ' ')
      .replace(/<template[\s\S]*?<\/template>/gi, ' ')
  );

  const images = Array.from(safeHtml.matchAll(/<img\b[^>]*>/gi)).map(match => {
    const tag = match[0];
    const srcMatch = tag.match(/\ssrc=["']([^"']*)["']/i);
    const hasAlt = /\s+alt\s*=/.test(tag);
    const altMatch = tag.match(/\salt=["']([^"']*)["']/i);

    return {
      src: srcMatch ? srcMatch[1] : '',
      alt: hasAlt ? (altMatch ? decodeHtmlEntities(altMatch[1]) : '') : null
    };
  });

  const headings = Array.from(safeHtml.matchAll(/<(h[1-6])[^>]*>([\s\S]*?)<\/\1>/gi)).map(match => ({
    tag: match[1].toLowerCase(),
    text: stripTags(match[2])
  }));

  return {
    title: titleMatch ? stripTags(titleMatch[1]) : '',
    h1,
    meta,
    content,
    schema,
    links,
    images,
    headings
  };
}

function buildAltCheckFromImages(images) {
  const altMissing = images.filter(img => img.alt === null).length;
  const altEmpty = images.filter(img => img.alt !== null && img.alt.trim() === '').length;

  return {
    imageCount: images.length,
    altMissing,
    altEmpty,
    preview: images.slice(0, 10)
  };
}

function buildHeadingCheckFromHeadings(headings) {
  const count = headings.reduce((acc, heading) => {
    acc[heading.tag] = (acc[heading.tag] || 0) + 1;
    return acc;
  }, {});

  const errors = [];
  const h1Count = count.h1 || 0;

  if (h1Count > 1) {
    errors.push('Mehr als eine H1 gefunden');
  } else if (h1Count === 0) {
    errors.push('Keine H1 gefunden');
  }

  const emptyHeadings = headings.filter(heading => heading.text === '');
  if (emptyHeadings.length > 0) {
    errors.push(`${emptyHeadings.length} leere Überschrift(en)`);
  }

  return {
    count,
    list: headings.slice(0, 10),
    errors
  };
}

function detectRenderMode(html, contentLength) {
  const hasAppRoot = /<div\s+id=["'](?:app|root)["'][^>]*>/i.test(html);
  const hasLargeBundle = /<script[^>]+(?:src=["'][^"']*(?:bundle|main|app|chunk)[^"']*\.js[^"']*["'])[^>]*>/i.test(html);
  const isContentVerySmall = contentLength < 200;

  return hasAppRoot || hasLargeBundle || isContentVerySmall;
}

async function fetchRawHtml(url, timeoutMs) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(url, {
      method: 'GET',
      redirect: 'follow',
      signal: controller.signal,
      headers: {
        'user-agent': 'Mozilla/5.0 (compatible; OS-Scanner/1.0)'
      }
    });

    const html = await response.text();

    return {
      status: response.status,
      html,
      contentLength: Buffer.byteLength(html, 'utf8'),
      finalUrl: response.url || url
    };
  } finally {
    clearTimeout(timeout);
  }
}

async function asyncPool(poolLimit, array, iteratorFn) {
  const ret = [];
  const executing = [];

  for (const item of array) {
    const p = Promise.resolve().then(() => iteratorFn(item));
    ret.push(p);

    if (poolLimit <= array.length) {
      const e = p.then(() => {
        const index = executing.indexOf(e);
        if (index >= 0) {
          executing.splice(index, 1);
        }
      });

      executing.push(e);

      if (executing.length >= poolLimit) {
        await Promise.race(executing);
      }
    }
  }

  return Promise.allSettled(ret);
}

function writeProgress(resultDir, payload) {
  fs.writeFileSync(path.join(resultDir, 'progress.json'), JSON.stringify(payload));
}

function publishCrawlProgress({ logger, resultDir, total, scannedPages, queueSize, currentUrl, stage, status = 'running' }) {
  const event = {
    type: 'crawl_progress',
    scanned_pages: scannedPages,
    queue_size: queueSize,
    current_url: currentUrl,
  };

  logger.info('crawl_progress', event);
  process.stdout.write(`${JSON.stringify(event)}\n`);

  writeProgress(resultDir, {
    current: scannedPages,
    total,
    status,
    stage,
    current_url: currentUrl,
    scanned_pages: scannedPages,
    queue_size: queueSize,
    type: 'crawl_progress',
  });
}

function publishPageScanned({ logger, url, status, altCount, headingCount, error = null }) {
  const event = {
    type: 'page_scanned',
    url,
    status,
    alt_count: altCount,
    heading_count: headingCount,
    error,
  };

  logger.info('page_scanned', event);
  process.stdout.write(`${JSON.stringify(event)}\n`);
}

function createFingerprint({ title = '', h1 = [], content = '', html = '' }) {
  return {
    title: String(title || ''),
    h1: Array.isArray(h1) ? h1 : [],
    content_length: String(content || '').length,
    content_hash: crypto.createHash('sha1').update(String(html || ''), 'utf8').digest('hex')
  };
}

function normalizeSchemaBlocks(schema) {
  if (!Array.isArray(schema)) {
    return '';
  }

  return schema
    .map(item => String(item || '').replace(/\s+/g, ' ').trim())
    .filter(Boolean)
    .sort()
    .join('\n');
}

function getPreviousScanIdFromOptions(scanOptions) {
  return scanOptions.previous_scan_id
    || scanOptions.previousScanId
    || scanOptions.previous_scan
    || scanOptions.previousScan
    || null;
}

function loadPreviousResults(scanOptions) {
  const previousScanId = getPreviousScanIdFromOptions(scanOptions);

  if (!previousScanId) {
    return new Map();
  }

  const previousDir = path.resolve(__dirname, '..', '..', 'storage', 'scans', String(previousScanId));

  if (!fs.existsSync(previousDir)) {
    log(`Kein vorheriger Scan gefunden: ${previousScanId}`);
    return new Map();
  }

  const entries = fs.readdirSync(previousDir)
    .filter(file => file.endsWith('.json') && file !== 'progress.json');

  const resultMap = new Map();

  entries.forEach((file) => {
    try {
      const payload = JSON.parse(fs.readFileSync(path.join(previousDir, file), 'utf8'));
      if (payload && payload.url) {
        resultMap.set(payload.url, payload);
      }
    } catch (err) {
      log(`Vorheriges Ergebnis konnte nicht gelesen werden (${file}): ${err.message}`);
    }
  });

  log(`Vorherige Ergebnisse geladen: ${resultMap.size} (scanId=${previousScanId})`);
  return resultMap;
}

let options;

try {
  options = JSON.parse(process.argv[2]);
} catch (err) {
  rootLogger.error('scan_error', { error: 'Ungültige Optionen', details: err.message });
  console.error(JSON.stringify({ error: 'Ungültige Optionen', details: err.message }));
  process.exit(1);
}

const scanId = options.scan_id || process.argv[3] || 'unknown';
const resultDir = path.resolve(__dirname, '..', '..', 'storage', 'scans', scanId);

if (!fs.existsSync(resultDir)) {
  fs.mkdirSync(resultDir, { recursive: true });
}

(async () => {
  const logger = rootLogger.child({ scan_id: scanId });
  const checks = Array.isArray(options.checks) ? options.checks : [];
  const maxPages = toPositiveInt(options.max_pages ?? options.maxPages, 20);
  const maxDepth = toPositiveInt(options.max_depth ?? options.maxDepth, 2);
  const maxParallelPages = toPositiveInt(
    options.max_parallel_pages
      ?? options.maxParallelPages
      ?? options.scan_concurrency
      ?? options.scanConcurrency
      ?? process.env.CRAWLER_CONCURRENCY,
    6
  );
  const pageTimeoutSeconds = toPositiveInt(options.page_timeout ?? options.pageTimeout, 30);
  const maxRetries = toPositiveInt(options.max_retries ?? options.maxRetries, 3);
  const retryDelaySeconds = toPositiveInt(options.retry_delay ?? options.retryDelay, 10);
  const maxScanTimeSeconds = toPositiveInt(options.max_scan_time ?? options.maxScanTime, 300);

  const pageTimeoutMs = pageTimeoutSeconds * 1000;
  const retryDelayMs = retryDelaySeconds * 1000;
  const maxScanTimeMs = maxScanTimeSeconds * 1000;

  logger.info('scan_started', {
    url: options.url,
    checks,
    max_pages: maxPages,
    max_depth: maxDepth,
    max_parallel_pages: maxParallelPages,
    max_scan_time: maxScanTimeSeconds,
  });
  const previousResultsByUrl = loadPreviousResults(options);

  const browser = await puppeteer.launch({ headless: true });
  const abortPath = path.resolve(__dirname, '..', '..', 'storage', 'app', `abort-${scanId}.flag`);
  const startedAt = Date.now();

  const hasExceededMaxScanTime = () => Date.now() - startedAt >= maxScanTimeMs;

  const configurePage = async (page) => {
    await page.setRequestInterception(true);
    page.on('request', (req) => {
      const type = req.resourceType();
      const requestUrl = req.url().toLowerCase();
      const isAnalyticsRequest = /google-analytics|googletagmanager|doubleclick|mixpanel|segment|hotjar|plausible|matomo/.test(requestUrl);

      if (['image', 'font', 'media'].includes(type) || isAnalyticsRequest) {
        req.abort();
      } else {
        req.continue();
      }
    });
  };

  const seedPage = await browser.newPage();
  await configurePage(seedPage);

  let absoluteUrls = [];

  try {
    absoluteUrls = await crawlLinks(seedPage, options.url, {
      ...options,
      scan_id: scanId,
      max_pages: maxPages,
      max_depth: maxDepth,
      page_timeout: pageTimeoutSeconds,
      max_retries: maxRetries,
      retry_delay: retryDelaySeconds,
      logger,
      onProgress: ({ scanned_pages, queue_size, current_url }) => {
        publishCrawlProgress({
          logger,
          resultDir,
          total: maxPages,
          scannedPages: scanned_pages,
          queueSize: queue_size,
          currentUrl: current_url,
          stage: 'crawling',
        });
      },
    });
  } catch (err) {
    logger.error('scan_error', { url: options.url, error: err.message });
  } finally {
    await seedPage.close();
  }

  if (absoluteUrls.length === 1 && absoluteUrls[0] === options.url) {
    logger.warn('crawl_progress', { url: options.url, warning: 'only_seed_url_discovered' });
  }

  if (absoluteUrls.length === 0) {
    log(`[scanner] crawl returned no urls | seed=${options.url}`);
  }

  const normalizedSeedUrl = normalizeUrl(options.url) || options.url;

  if (!absoluteUrls.includes(normalizedSeedUrl) && absoluteUrls.length < maxPages) {
    log(`[scanner] seed url injected into queue | url=${normalizedSeedUrl}`);
    absoluteUrls.unshift(normalizedSeedUrl);
  }

  const normalizedQueuedUrls = new Set();
  const deduplicatedUrls = [];

  for (const discoveredUrl of absoluteUrls) {
    const normalized = normalizeUrl(discoveredUrl, options.url);

    if (!normalized || normalizedQueuedUrls.has(normalized)) {
      continue;
    }

    normalizedQueuedUrls.add(normalized);
    deduplicatedUrls.push(normalized);
  }

  absoluteUrls = deduplicatedUrls.slice(0, maxPages);

  log(`URLs gefunden: ${absoluteUrls.length}`);

  writeProgress(resultDir, {
    current: 0,
    total: absoluteUrls.length,
    status: 'running',
    stage: 'scanning',
    current_url: null,
    scanned_pages: 0,
    queue_size: absoluteUrls.length,
    type: 'crawl_progress',
  });

  let completed = 0;
  let failedByTimeout = false;
  const emittedPageScannedUrls = new Set();

  const runTask = async ({ url, position }) => {
    if (fs.existsSync(abortPath) || hasExceededMaxScanTime()) {
      return;
    }

    let page;
    const result = { url };
    let success = false;

    try {
      page = await browser.newPage();
      await configurePage(page);
      let currentExtraction = {
        title: '',
        h1: [],
        content: '',
        schema: [],
        html: ''
      };

      for (let attempt = 1; attempt <= maxRetries; attempt += 1) {
        if (fs.existsSync(abortPath) || hasExceededMaxScanTime()) {
          break;
        }

        try {
          const preFetched = await fetchRawHtml(url, pageTimeoutMs);
          const renderMode = detectRenderMode(preFetched.html, preFetched.contentLength);

          log(`URL ${url} | status=${preFetched.status} | contentLength=${preFetched.contentLength} | mode=${renderMode ? 'render' : 'html'}`);

          result.title = '';

          if (checks.includes('status')) {
            result.statusCheck = {
              status: preFetched.status,
              redirected: preFetched.finalUrl && preFetched.finalUrl !== url,
              finalUrl: preFetched.finalUrl || url,
              error: null
            };
          }

          if (!renderMode) {
            const extracted = extractFromHtml(preFetched.html);
            currentExtraction = {
              title: extracted.title,
              h1: extracted.h1,
              content: extracted.content,
              schema: extracted.schema,
              html: preFetched.html
            };
            result.title = extracted.title;

            if (checks.includes('alt')) {
              result.altCheck = buildAltCheckFromImages(extracted.images);
            }

            if (checks.includes('heading')) {
              result.headingCheck = buildHeadingCheckFromHeadings(extracted.headings);
            }

            log(`HTML mode used for ${url}`);
          } else {
            const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });

            result.title = await page.title();

            if (options.checks.includes('status')) {
              result.statusCheck = {
                status: response ? response.status() : 'unknown',
                redirected: response ? response.url() !== url : false,
                finalUrl: response ? response.url() : url,
                error: null
              };
            }

            const renderedExtraction = await page.evaluate(() => {
              const h1 = Array.from(document.querySelectorAll('h1')).map(element => element.innerText || '');
              const meta = Array.from(document.querySelectorAll('meta')).map(element => element.outerHTML || '');
              const schema = Array.from(document.querySelectorAll('script[type="application/ld+json"]')).map(element => element.innerText || '');
              const links = Array.from(document.querySelectorAll('a[href]')).map(element => element.getAttribute('href') || '').filter(Boolean);
              const clone = document.body.cloneNode(true);
              clone.querySelectorAll('script, style, noscript, template').forEach(element => element.remove());
              const content = clone.innerText || '';
              return { h1, meta, schema, links, content };
            });

            const renderedHtml = await page.content();
            currentExtraction = {
              title: result.title,
              h1: renderedExtraction.h1,
              content: renderedExtraction.content,
              schema: renderedExtraction.schema,
              html: renderedHtml
            };

            if (checks.includes('alt')) {
              result.altCheck = await altCheck(page);
            }

            if (checks.includes('heading')) {
              result.headingCheck = await headingCheck(page);
            }

            log(
              `Render mode used for ${url} | h1=${renderedExtraction.h1.length} | meta=${renderedExtraction.meta.length} | schema=${renderedExtraction.schema.length} | links=${renderedExtraction.links.length} | contentLength=${renderedExtraction.content.length}`
            );
          }

          result.fingerprint = createFingerprint(currentExtraction);

          const previousResult = previousResultsByUrl.get(url);
          const previousFingerprint = previousResult && previousResult.fingerprint ? previousResult.fingerprint : null;

          const titleChanged = !previousResult
            ? true
            : String(previousFingerprint?.title || '') !== String(currentExtraction.title || '');
          const h1Changed = !previousResult
            ? true
            : JSON.stringify(previousFingerprint?.h1 || []) !== JSON.stringify(currentExtraction.h1 || []);
          const contentChanged = !previousResult
            ? true
            : Number(previousFingerprint?.content_length || 0) !== String(currentExtraction.content || '').length
              || String(previousFingerprint?.content_hash || '') !== String(result.fingerprint.content_hash || '');
          const schemaChanged = !previousResult
            ? true
            : normalizeSchemaBlocks(previousResult.schema) !== normalizeSchemaBlocks(currentExtraction.schema);

          result.changes = {
            title_changed: titleChanged,
            content_changed: contentChanged,
            h1_changed: h1Changed,
            schema_changed: schemaChanged
          };

          const isFingerprintIdentical = Boolean(previousFingerprint)
            && String(previousFingerprint.title || '') === String(result.fingerprint.title || '')
            && JSON.stringify(previousFingerprint.h1 || []) === JSON.stringify(result.fingerprint.h1 || [])
            && Number(previousFingerprint.content_length || 0) === Number(result.fingerprint.content_length || 0)
            && String(previousFingerprint.content_hash || '') === String(result.fingerprint.content_hash || '');

          if (isFingerprintIdentical) {
            if (checks.includes('alt') && previousResult.altCheck) {
              result.altCheck = previousResult.altCheck;
            }

            if (checks.includes('heading') && previousResult.headingCheck) {
              result.headingCheck = previousResult.headingCheck;
            }

            result.incremental = {
              skipped_heavy_analysis: true,
              reused_previous_scores: true
            };

            log(`Unverändert erkannt: ${url} | Heavy-Analyse übersprungen, Ergebnisse wiederverwendet`);
          } else {
            result.incremental = {
              skipped_heavy_analysis: false,
              reused_previous_scores: false
            };

            const detectedChanges = [];
            if (titleChanged) detectedChanges.push('title changed');
            if (h1Changed) detectedChanges.push('h1 changed');
            if (contentChanged) detectedChanges.push('content changed');
            if (schemaChanged) detectedChanges.push('schema changed');

            if (detectedChanges.length > 0) {
              log(`Änderungen erkannt bei ${url}: ${detectedChanges.join(', ')}`);
            }
          }

          success = true;
          break;
        } catch (err) {
          result.error = err.message;

          if (checks.includes('status') && !result.statusCheck) {
            result.statusCheck = {
              status: 'error',
              redirected: false,
              finalUrl: url,
              error: err.message
            };
          }

          log(`Fehler bei ${url} (Versuch ${attempt}/${maxRetries}): ${err.message}`);

          if (attempt < maxRetries) {
            await sleep(retryDelayMs);
          }
        }
      }

      if (!success && !result.error) {
        result.error = 'Scan abgebrochen (Abort-Flag oder max_scan_time erreicht).';
      }
    } finally {
      fs.writeFileSync(path.join(resultDir, `${position}.json`), JSON.stringify(result, null, 2));

      const normalizedResultUrl = normalizeUrl(url, options.url) || url;
      if (!emittedPageScannedUrls.has(normalizedResultUrl)) {
        emittedPageScannedUrls.add(normalizedResultUrl);

        publishPageScanned({
          logger,
          url: normalizedResultUrl,
          status: result.statusCheck?.status ?? null,
          altCount: result.altCheck?.altMissing ?? 0,
          headingCount: Array.isArray(result.headingCheck?.list) ? result.headingCheck.list.length : 0,
          error: result.error ?? null,
        });
      }

      completed += 1;
      const queueSize = Math.max(absoluteUrls.length - completed, 0);
      const progressStatus = fs.existsSync(abortPath) ? 'aborted' : (failedByTimeout ? 'failed' : 'running');

      publishCrawlProgress({
        logger,
        resultDir,
        total: absoluteUrls.length,
        scannedPages: completed,
        queueSize,
        currentUrl: url,
        stage: 'scanning',
        status: progressStatus,
      });

      if (page) {
        try {
          await page.close();
        } catch (closeError) {
          logger.warn('page_close_failed', { url, error: closeError.message });
        }
      }
    }
  };

  const scanTimeoutHandle = setTimeout(() => {
    failedByTimeout = true;
    logger.error('scan_error', {
      reason: 'max_scan_time_exceeded',
      max_scan_time: maxScanTimeSeconds,
    });
    log(`Scan fehlgeschlagen (max_scan_time überschritten): ${scanId}`);
  }, maxScanTimeMs);

  try {
    const queueItems = absoluteUrls.map((url, position) => ({ url, position }));

    await asyncPool(maxParallelPages, queueItems, async (item) => {
      if (failedByTimeout || fs.existsSync(abortPath) || hasExceededMaxScanTime()) {
        return;
      }

      await runTask(item);

      if (hasExceededMaxScanTime()) {
        failedByTimeout = true;
      }
    });
  } finally {
    clearTimeout(scanTimeoutHandle);
  }

  if (fs.existsSync(abortPath)) {
    logger.warn('scan_error', {
      reason: 'scan_aborted',
    });
    log(`Scan abgebrochen: ${scanId}`);
    writeProgress(resultDir, {
      current: completed,
      total: absoluteUrls.length,
      status: 'aborted',
      stage: 'aborted',
      current_url: null,
      scanned_pages: completed,
      queue_size: Math.max(absoluteUrls.length - completed, 0),
      type: 'crawl_progress',
    });
  } else if (failedByTimeout || hasExceededMaxScanTime()) {
    logger.error('scan_error', {
      reason: 'scan_failed',
    });
    log(`Scan beendet mit Fehlerstatus: ${scanId}`);
    writeProgress(resultDir, {
      current: completed,
      total: absoluteUrls.length,
      status: 'failed',
      stage: 'failed',
      current_url: null,
      scanned_pages: completed,
      queue_size: Math.max(absoluteUrls.length - completed, 0),
      type: 'crawl_progress',
    });
  } else {
    logger.info('scan_finished', {
      pages_crawled: completed,
      total: absoluteUrls.length,
    });
    log(`Scan abgeschlossen: ${scanId}`);
    writeProgress(resultDir, {
      current: absoluteUrls.length,
      total: absoluteUrls.length,
      status: 'done',
      stage: 'completed',
      current_url: null,
      scanned_pages: absoluteUrls.length,
      queue_size: 0,
      type: 'crawl_progress',
    });
  }

  await browser.close();
})();
