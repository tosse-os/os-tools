const puppeteer = require('puppeteer');
const path = require('path');
const altCheck = require('../checks/altCheck');
const headingCheck = require('../checks/headingCheck');
const statusCheck = require('../checks/statusCheck');
const crawlLinks = require('../crawl/crawlLinks');
const { createStructuredLogger } = require('../utils/structuredLogger');

const logFile = path.resolve(__dirname, '..', '..', 'storage', 'logs', 'node-scanner.log');
const logger = createStructuredLogger({
  logFilePath: logFile,
  output: process.stderr,
});

let options;
try {
  options = JSON.parse(process.argv[2]);
} catch (err) {
  logger.error('scan_error', { error: 'Ungültige Optionen', details: err.message });
  console.error(JSON.stringify({ error: 'Ungültige Optionen', details: err.message }));
  process.exit(1);
}

(async () => {
  const checks = Array.isArray(options.checks) ? options.checks : [];
  const scanId = options.scan_id || options.scanId || 'unknown';
  const startUrl = options.url;
  const scanLogger = logger.child({ scan_id: scanId });

  scanLogger.info('scan_started', {
    url: startUrl,
    checks,
    max_pages: options.max_pages,
    max_depth: options.max_depth,
    max_scan_time: options.max_scan_time,
  });

  const results = [];
  let browser;

  try {
    browser = await puppeteer.launch({ headless: 'new' });
    const page = await browser.newPage();

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
      logger: scanLogger,
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
    scanLogger.info('scan_finished', {
      url: options.url,
      pages_crawled: result.pages_crawled,
    });
  } catch (e) {
    scanLogger.error('scan_error', {
      url: options.url,
      error: e.message,
    });
    results.push({ url: options.url, error: e.message });
  } finally {
    if (browser) {
      await browser.close();
    }
  }

  console.log(JSON.stringify(results));
})();
