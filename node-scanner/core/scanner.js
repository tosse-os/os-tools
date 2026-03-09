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

let options;
try {
  options = JSON.parse(process.argv[2]);
} catch (err) {
  console.error(JSON.stringify({ error: 'Ungültige Optionen', details: err.message }));
  process.exit(1);
}

(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();

  const results = [];

  try {
    await page.goto(options.url, { waitUntil: 'domcontentloaded', timeout: 20000 });

    const result = {
      url: options.url,
      title: await page.title()
    };

    if (options.checks.includes('status')) {
      result.statusCheck = await statusCheck(page, options.url);
    }
    if (options.checks.includes('alt')) {
      result.altCheck = await altCheck(page);
    }
    if (options.checks.includes('heading')) {
      result.headingCheck = await headingCheck(page);
    }

    const linkGraph = await crawlLinks(page, options.url, {
      max_pages: options.max_pages,
      max_depth: options.max_depth,
      max_scan_time: options.max_scan_time,
      page_timeout: options.page_timeout,
      max_retries: options.max_retries,
      retry_delay: options.retry_delay,
      include_link_graph: true,
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
  } catch (e) {
    results.push({ url: options.url, error: e.message });
  }

  await browser.close();

  console.log(JSON.stringify(results));
})();
