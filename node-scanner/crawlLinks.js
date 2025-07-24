const { URL } = require('url');

module.exports = async function crawlLinks(page, startUrl, maxUrls = 10) {
  const visited = new Set();
  const queue = [startUrl];
  const origin = new URL(startUrl).origin;

  while (queue.length && visited.size < maxUrls) {
    const url = queue.shift();
    if (visited.has(url)) continue;

    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });

      const links = await page.$$eval('a[href]', (as) =>
        as.map((a) => a.href).filter((href) => href.startsWith('http'))
      );

      for (const link of links) {
        const u = new URL(link);
        if (u.origin === origin && !visited.has(link) && !queue.includes(link)) {
          queue.push(link);
        }
      }

      visited.add(url);
    } catch (e) {
      // überspringen bei Fehler
    }
  }

  return Array.from(visited).slice(0, maxUrls);
};
