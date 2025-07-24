const puppeteer = require('puppeteer');
const altCheck = require('./checks/altCheck');
const headingCheck = require('./checks/headingCheck');
const statusCheck = require('./checks/statusCheck');

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
    //require('fs').writeFileSync('./node-scanner/final-data.txt', JSON.stringify(result, null, 2));
    results.push(result);
  } catch (e) {
    results.push({ url: options.url, error: e.message });
  }

  await browser.close();

// Alte Ausgabe – NICHT GEEIGNET für Laravel-Job
// for (const row of results) {
//   console.log(JSON.stringify(row));
// }

// Neue Ausgabe – genau ein JSON-Array
console.log(JSON.stringify(results));

})();
