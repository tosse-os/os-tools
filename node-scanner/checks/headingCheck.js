module.exports = async function headingCheck(page) {
  const headings = await page.$$eval('h1, h2, h3, h4, h5, h6', nodes =>
    nodes.map(el => ({
      tag: el.tagName.toLowerCase(),
      text: el.textContent.trim()
    }))
  );

  const count = headings.reduce((acc, h) => {
    acc[h.tag] = (acc[h.tag] || 0) + 1;
    return acc;
  }, {});

  const errors = [];

  const h1Count = count.h1 || 0;
  if (h1Count > 1) errors.push('Mehr als eine H1 gefunden');
  else if (h1Count === 0) errors.push('Keine H1 gefunden');

  const emptyHeadings = headings.filter(h => h.text === '');
  if (emptyHeadings.length > 0) errors.push(`${emptyHeadings.length} leere Überschrift(en)`);

  return {
    count,
    list: headings.slice(0, 10),
    errors
  };
};
