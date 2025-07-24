const puppeteer = require('puppeteer');
const altCheck = require('./checks/altCheck');
const headingCheck = require('./checks/headingCheck');
const statusCheck = require('./checks/statusCheck');
const { URL } = require('url');

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
  const visited = new Set();
  const queue = [options.url];
  const results = [];
  const maxPages = options.maxPages || 5;

  while (queue.length && results.length < maxPages) {
    const url = queue.shift();
    const normalizedUrl = url.replace(/\/$/, '');
    if (visited.has(normalizedUrl)) continue;
    visited.add(normalizedUrl);

    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20000 });

      const result = {
        url,
        title: await page.title()
      };

      if (options.checks.includes('status')) result.statusCheck = await statusCheck(page, url);
      if (options.checks.includes('alt')) result.altCheck = await altCheck(page);
      if (options.checks.includes('heading')) result.headingCheck = await headingCheck(page);

      results.push(result);

      const hrefs = await page.$$eval('a[href]', links =>
        links
          .map(link => link.getAttribute('href'))
          .filter(Boolean)
          .filter(href => !href.startsWith('#'))
          .filter(href => !href.startsWith('mailto:'))
          .filter(href => !href.startsWith('tel:'))
          .filter(href => !href.startsWith('javascript:'))
          .filter(href => !href.includes('#'))

      );

      const baseUrl = new URL(url).origin;

      for (const href of hrefs) {
        try {
          const absoluteUrl = new URL(href, url);
          let normalized = absoluteUrl.href.replace(/\/$/, ''); // Trailing Slash entfernen

          if (absoluteUrl.origin === baseUrl && !visited.has(normalized)) {
            queue.push(normalized);
          }
        } catch {
          continue;
        }
      }
    } catch (err) {
      console.error(JSON.stringify({ error: 'Fehler beim Verarbeiten der URL', url, details: err.message }));
      continue;
    }
  }

  await browser.close();
  console.log(JSON.stringify(results));
})();
