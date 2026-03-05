const puppeteer = require('puppeteer');
const altCheck = require('../checks/altCheck');
const headingCheck = require('../checks/headingCheck');
const statusCheck = require('../checks/statusCheck');
const { collectUniqueUrls } = require('../utils/urlUtils');
const fs = require('fs');
const path = require('path');

const logFile = path.resolve(__dirname, '..', 'storage', 'logs', 'node-scanner.log');

function log(message) {
  try {
    fs.appendFileSync(logFile, `[${new Date().toISOString()}] ${message}\n`);
  } catch { }
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

const resultDir = path.resolve(__dirname, '..', 'storage', 'scans', scanId);

if (!fs.existsSync(resultDir)) {
  fs.mkdirSync(resultDir, { recursive: true });
}

(async () => {

  log(`Scan gestartet: ${options.url} (scanId=${scanId})`);

  const browser = await puppeteer.launch({ headless: true });

  const maxPages = options.maxPages || 20;
  const maxConcurrency = 3;

  const abortPath = path.resolve(__dirname, '..', 'storage', 'app', `abort-${scanId}.flag`);

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

  const hrefs = await page.$$eval('a[href]', links =>
    links
      .map(link => link.getAttribute('href'))
      .filter(Boolean)
      .filter(href => !href.startsWith('#'))
      .filter(href => !href.startsWith('mailto:'))
      .filter(href => !href.startsWith('tel:'))
      .filter(href => !href.startsWith('javascript:'))
  );

  const absoluteUrls = collectUniqueUrls(options.url, hrefs).slice(0, maxPages);

  log(`URLs gefunden: ${absoluteUrls.length}`);

  await page.close();

  fs.writeFileSync(
    path.join(resultDir, `progress.json`),
    JSON.stringify({
      current: 0,
      total: absoluteUrls.length,
      status: 'running'
    })
  );

  let currentIndex = 0;

  const runTask = async (url, position) => {

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

    let result = { url };

    try {

      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 10000 });

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

    } catch (err) {

      result.error = err.message;

      log(`Fehler bei ${url}: ${err.message}`);

    }

    fs.writeFileSync(
      path.join(resultDir, `${position}.json`),
      JSON.stringify(result, null, 2)
    );

    const newCurrent = position + 1;

    fs.writeFileSync(
      path.join(resultDir, `progress.json`),
      JSON.stringify({
        current: newCurrent,
        total: absoluteUrls.length,
        status: fs.existsSync(abortPath) ? 'aborted' : 'running'
      })
    );

    await page.close();
  };

  while (currentIndex < absoluteUrls.length) {

    if (fs.existsSync(abortPath)) {

      log(`Scan abgebrochen: ${scanId}`);

      fs.writeFileSync(
        path.join(resultDir, `progress.json`),
        JSON.stringify({
          current: currentIndex,
          total: absoluteUrls.length,
          status: 'aborted'
        })
      );

      break;
    }

    const batch = [];

    while (batch.length < maxConcurrency && currentIndex < absoluteUrls.length) {

      const position = currentIndex;
      const url = absoluteUrls[position];

      batch.push(runTask(url, position));

      currentIndex++;
    }

    await Promise.allSettled(batch);
  }

  if (!fs.existsSync(abortPath)) {

    log(`Scan abgeschlossen: ${scanId}`);

    fs.writeFileSync(
      path.join(resultDir, `progress.json`),
      JSON.stringify({
        current: absoluteUrls.length,
        total: absoluteUrls.length,
        status: 'done'
      })
    );
  }

  await browser.close();

})();
