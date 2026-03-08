const puppeteer = require('puppeteer');
const altCheck = require('../checks/altCheck');
const headingCheck = require('../checks/headingCheck');
const statusCheck = require('../checks/statusCheck');
const crawlLinks = require('../crawl/crawlLinks');
const fs = require('fs');
const path = require('path');

const logFile = path.resolve(__dirname, '..', 'storage', 'logs', 'node-scanner.log');

function log(message) {
  try {
    fs.appendFileSync(logFile, `[${new Date().toISOString()}] ${message}\n`);
  } catch { }
}

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function toPositiveInt(value, fallback) {
  const num = Number(value);
  return Number.isFinite(num) && num > 0 ? Math.floor(num) : fallback;
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

let options;

try {
  options = JSON.parse(process.argv[2]);
} catch (err) {
  log(`Scanner Options Fehler: ${err.message}`);
  console.error(JSON.stringify({ error: 'Ungültige Optionen', details: err.message }));
  process.exit(1);
}

const scanId = process.argv[3];
const resultDir = path.resolve(__dirname, '..', '..', 'storage', 'scans', scanId);

if (!fs.existsSync(resultDir)) {
  fs.mkdirSync(resultDir, { recursive: true });
}

(async () => {
  const maxPages = toPositiveInt(options.max_pages ?? options.maxPages, 20);
  const maxDepth = toPositiveInt(options.max_depth ?? options.maxDepth, 2);
  const maxParallelPages = toPositiveInt(options.max_parallel_pages ?? options.maxParallelPages, 3);
  const pageTimeoutSeconds = toPositiveInt(options.page_timeout ?? options.pageTimeout, 30);
  const maxRetries = toPositiveInt(options.max_retries ?? options.maxRetries, 3);
  const retryDelaySeconds = toPositiveInt(options.retry_delay ?? options.retryDelay, 10);
  const maxScanTimeSeconds = toPositiveInt(options.max_scan_time ?? options.maxScanTime, 300);

  const pageTimeoutMs = pageTimeoutSeconds * 1000;
  const retryDelayMs = retryDelaySeconds * 1000;
  const maxScanTimeMs = maxScanTimeSeconds * 1000;

  log(`Scan gestartet: ${options.url} (scanId=${scanId})`);

  const browser = await puppeteer.launch({ headless: true });
  const abortPath = path.resolve(__dirname, '..', '..', 'storage', 'app', `abort-${scanId}.flag`);
  const startedAt = Date.now();

  const hasExceededMaxScanTime = () => Date.now() - startedAt >= maxScanTimeMs;

  const configurePage = async (page) => {
    await page.setRequestInterception(true);
    page.on('request', (req) => {
      const type = req.resourceType();
      if (['image', 'stylesheet', 'font', 'media'].includes(type)) {
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
      max_pages: maxPages,
      max_depth: maxDepth,
      page_timeout: pageTimeoutSeconds,
      max_retries: maxRetries,
      retry_delay: retryDelaySeconds
    });
  } catch (err) {
    log(`Fehler beim Crawlen der Startseite ${options.url}: ${err.message}`);
  } finally {
    await seedPage.close();
  }

  if (!absoluteUrls.includes(options.url) && absoluteUrls.length < maxPages) {
    absoluteUrls.unshift(options.url);
  }

  absoluteUrls = absoluteUrls.slice(0, maxPages);

  log(`URLs gefunden: ${absoluteUrls.length}`);

  writeProgress(resultDir, {
    current: 0,
    total: absoluteUrls.length,
    status: 'running'
  });

  let completed = 0;
  let failedByTimeout = false;

  const runTask = async ({ url, position }) => {
    if (fs.existsSync(abortPath) || hasExceededMaxScanTime()) {
      return;
    }

    const page = await browser.newPage();
    await configurePage(page);

    const result = { url };
    let success = false;

    try {
      for (let attempt = 1; attempt <= maxRetries; attempt += 1) {
        if (fs.existsSync(abortPath) || hasExceededMaxScanTime()) {
          break;
        }

        try {
          await page.goto(url, { waitUntil: 'domcontentloaded', timeout: pageTimeoutMs });

          result.title = await page.title();

          if (options.checks.includes('status')) {
            result.statusCheck = await statusCheck(page, url);
          }

          if (options.checks.includes('alt')) {
            result.altCheck = await altCheck(page);
          }

          if (options.checks.includes('heading')) {
            result.headingCheck = await headingCheck(page);
          }

          success = true;
          break;
        } catch (err) {
          result.error = err.message;
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
      completed += 1;
      writeProgress(resultDir, {
        current: completed,
        total: absoluteUrls.length,
        status: fs.existsSync(abortPath) ? 'aborted' : (failedByTimeout ? 'failed' : 'running')
      });

      await page.close();
    }
  };

  const scanTimeoutHandle = setTimeout(() => {
    failedByTimeout = true;
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
    log(`Scan abgebrochen: ${scanId}`);
    writeProgress(resultDir, {
      current: completed,
      total: absoluteUrls.length,
      status: 'aborted'
    });
  } else if (failedByTimeout || hasExceededMaxScanTime()) {
    log(`Scan beendet mit Fehlerstatus: ${scanId}`);
    writeProgress(resultDir, {
      current: completed,
      total: absoluteUrls.length,
      status: 'failed'
    });
  } else {
    log(`Scan abgeschlossen: ${scanId}`);
    writeProgress(resultDir, {
      current: absoluteUrls.length,
      total: absoluteUrls.length,
      status: 'done'
    });
  }

  await browser.close();
})();
