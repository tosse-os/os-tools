const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');
const altCheck = require('../checks/altCheck');
const headingCheck = require('../checks/headingCheck');
const statusCheck = require('../checks/statusCheck');
const crawlLinks = require('../crawl/crawlLinks');

const logFile = path.resolve(__dirname, '..', '..', 'storage', 'logs', 'node-scanner.log');

function log(message) {
  try {
    fs.mkdirSync(path.dirname(logFile), { recursive: true });
    fs.appendFileSync(logFile, `[${new Date().toISOString()}] ${message}\n`);
  } catch {
    // noop
  }
}

console.log('[SCANNER] start');
console.log('[SCAN TRACE] scanner_start', {
  args: process.argv,
});

let options;
try {
  options = JSON.parse(process.argv[2]);
} catch (err) {
  console.error('[SCANNER ERROR]', err);
  console.error(JSON.stringify({ error: 'Ungültige Optionen', details: err.message }));
  process.exit(1);
}

console.log('[SCAN TRACE] scanner_config', options);

(async () => {
  const checks = Array.isArray(options.checks) ? options.checks : [];
  const scanId = options.scan_id || options.scanId || 'unknown';
  const startUrl = options.url;

  console.log('[SCAN TRACE] crawler_start', {
    scan_id: scanId,
    startUrl,
  });
  console.log('[CRAWLER] visiting', startUrl);
  log(
    `[scanner] scan started | url=${options.url} | checks=${checks.join(',')} | max_pages=${options.max_pages ?? ''} | max_depth=${options.max_depth ?? ''} | max_scan_time=${options.max_scan_time ?? ''}`
  );

  const results = [];
  let browser;

  try {
    browser = await puppeteer.launch({ headless: 'new' });
    console.log('[SCAN TRACE] browser_launched', {
      scan_id: scanId,
    });
    const page = await browser.newPage();

    page.on('console', (msg) => {
      console.log('[PAGE CONSOLE]', msg.text());
    });

    page.on('request', (req) => {
      console.log('[PAGE REQUEST]', req.url());
    });

    page.on('response', (res) => {
      console.log('[PAGE RESPONSE]', res.status(), res.url());
    });

    page.on('requestfailed', (req) => {
      console.log('[PAGE REQUEST FAILED]', req.url());
    });

    page.on('error', (err) => {
      console.log('[PAGE ERROR]', err);
    });

    await page.goto(options.url, { waitUntil: 'domcontentloaded', timeout: 20000 });

    const result = {
      url: options.url,
      title: await page.title(),
    };

    if (checks.includes('status')) {
      result.statusCheck = await statusCheck(page, options.url);
    }
    if (checks.includes('alt')) {
      result.altCheck = await altCheck(page);
    }
    if (checks.includes('heading')) {
      result.headingCheck = await headingCheck(page);
    }

    // ensure crawler entrypoint is executed
    const linkGraph = await crawlLinks(page, options.url, {
      max_pages: options.max_pages,
      max_depth: options.max_depth,
      max_scan_time: options.max_scan_time,
      page_timeout: options.page_timeout,
      max_retries: options.max_retries,
      retry_delay: options.retry_delay,
      include_link_graph: true,
      scan_id: scanId,
      logger: (message) => log(`[scanner] ${message}`),
    });

    const httpStatusCodes = {};
    if (result.statusCheck && result.statusCheck.status !== undefined) {
      httpStatusCodes[options.url] = result.statusCheck.status;
    }

    result.pages_crawled = Array.isArray(linkGraph.urls) ? linkGraph.urls.length : 0;
    result.internal_links = linkGraph.internal_links;
    result.page_depth = linkGraph.page_depth;
    result.incoming_links_count = linkGraph.incoming_links_count;
    result.outgoing_links_count = linkGraph.outgoing_links_count;
    result.orphan_pages = linkGraph.orphan_pages;
    result.link_graph_pages = linkGraph.pages;
    result.http_status_codes = httpStatusCodes;

    results.push(result);
    log(`[scanner] scan complete | url=${options.url} | pages_crawled=${result.pages_crawled}`);
  } catch (e) {
    console.error('[SCANNER ERROR]', e);
    results.push({ url: options.url, error: e.message });
    log(`[scanner] scan failed | url=${options.url} | message=${e.message}`);
  } finally {
    if (browser) {
      await browser.close();
    }
  }

  console.log(JSON.stringify(results));
})();
