const puppeteer = require('puppeteer');
const altCheck = require('./checks/altCheck');
const headingCheck = require('./checks/headingCheck');
const statusCheck = require('./checks/statusCheck');
const { URL } = require('url');
const fs = require('fs');
const path = require('path');

// === Optionen parsen ===
let options;
try {
  options = JSON.parse(process.argv[2]);
} catch (err) {
  console.error(JSON.stringify({ error: 'Ungültige Optionen', details: err.message }));
  process.exit(1);
}
console.log('📦 Optionen empfangen vom Laravel-Job:', options);


const scanId = process.argv[3];
const resultDir = path.resolve(__dirname, '..', 'storage', 'scans', scanId);
if (!fs.existsSync(resultDir)) {
  fs.mkdirSync(resultDir, { recursive: true });
}

// === URL-Normalisierung ===
function normalizeUrl(raw, base = '') {
  try {
    const url = new URL(raw, base);
    url.hash = '';
    url.search = '';
    return url.href.replace(/\/+$/, ''); // am Ende Slash(es) entfernen
  } catch {
    return null;
  }
}

(async () => {
  const browser = await puppeteer.launch({ headless: true });
  const maxPages = options.maxPages || 20;
  const maxConcurrency = 3;

  // === Schritt 1: Startseite laden, Links sammeln ===
  const page = await browser.newPage();
  await page.setRequestInterception(true);
  page.on('request', (req) => {
    const type = req.resourceType();
    if (['image', 'stylesheet', 'font', 'media'].includes(type)) {
      req.abort();
    } else {
      req.continue();
    }
  });

  await page.goto(options.url, { waitUntil: 'domcontentloaded', timeout: 10000 });

  let hrefs = await page.$$eval('a[href]', links =>
    links
      .map(link => link.getAttribute('href'))
      .filter(Boolean)
      .filter(href => !href.startsWith('#'))
      .filter(href => !href.startsWith('mailto:'))
      .filter(href => !href.startsWith('tel:'))
      .filter(href => !href.startsWith('javascript:'))
  );

  const baseUrl = new URL(options.url).origin;

  // Alle URLs absolut + normalisiert
  let absoluteUrls = hrefs.map(href => normalizeUrl(href, options.url)).filter(Boolean);

  // Startseite zuerst + alles deduplizieren + domain filtern
  absoluteUrls.unshift(normalizeUrl(options.url));
  absoluteUrls = [...new Set(absoluteUrls)].filter(url => url && url.startsWith(baseUrl));

  // Begrenzen auf maxPages
  const queue = absoluteUrls.slice(0, maxPages);
  await page.close();

  // === Fortschritt initialisieren ===
  fs.writeFileSync(
    path.join(resultDir, `progress.json`),
    JSON.stringify({ current: 0, total: queue.length })
  );

  let index = 0;

  const runTask = async (url) => {
    const page = await browser.newPage();

    await page.setRequestInterception(true);
    page.on('request', (req) => {
      const type = req.resourceType();
      if (['image', 'stylesheet', 'font', 'media'].includes(type)) {
        req.abort();
      } else {
        req.continue();
      }
    });

    let result;
    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 10000 });
      result = { url, title: await page.title() };

      if (options.checks.includes('status')) {
        console.log('✅ Status-Check wird ausgeführt für:', url);
        result.statusCheck = await statusCheck(page, url);
      }

      if (options.checks.includes('status')) result.statusCheck = await statusCheck(page, url);
      if (options.checks.includes('alt')) result.altCheck = await altCheck(page);
      if (options.checks.includes('heading')) result.headingCheck = await headingCheck(page);

    } catch (err) {
      result = { url, error: err.message };
    }

    fs.writeFileSync(path.join(resultDir, `${index}.json`), JSON.stringify(result, null, 2));
    index++;
    fs.writeFileSync(
      path.join(resultDir, `progress.json`),
      JSON.stringify({ current: index, total: queue.length })
    );

    await page.close();
  };

  // === Seiten parallel abarbeiten ===
  while (index < queue.length) {
    const batch = [];

    while (batch.length < maxConcurrency && index + batch.length < queue.length) {
      const url = queue[index + batch.length];
      batch.push(runTask(url));
    }

    await Promise.allSettled(batch);
  }

  await browser.close();
})();
