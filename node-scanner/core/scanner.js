const puppeteer = require('puppeteer');
const altCheck = require('../checks/altCheck');
const headingCheck = require('../checks/headingCheck');
const statusCheck = require('../checks/statusCheck');
const crawlLinks = require('../crawl/crawlLinks');

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
      require('fs').writeFileSync('./node-scanner/textstatus.txt', JSON.stringify(result, null, 2));
    }
    if (options.checks.includes('alt')) {
      result.altCheck = await altCheck(page);
      require('fs').writeFileSync('./node-scanner/textalt.txt', JSON.stringify(result, null, 2));
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
    });

    result.internal_links = linkGraph.internal_links;
    result.page_depth = linkGraph.page_depth;
    result.incoming_links_count = linkGraph.incoming_links_count;
    result.outgoing_links_count = linkGraph.outgoing_links_count;
    result.orphan_pages = linkGraph.orphan_pages;
    result.link_graph_pages = linkGraph.pages;

    results.push(result);
  } catch (e) {
    results.push({ url: options.url, error: e.message });
  }

  await browser.close();

  console.log(JSON.stringify(results));

})();
