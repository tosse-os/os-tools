module.exports = async function altCheck(page) {
  const images = await page.$$eval('img', imgs =>
    imgs.map(img => ({
      src: img.getAttribute('src') || '',
      alt: img.hasAttribute('alt') ? img.getAttribute('alt') : null
    }))
  );

  const altMissing = images.filter(img => img.alt === null).length;
  const altEmpty = images.filter(img => img.alt !== null && img.alt.trim() === '').length;

  return {
    imageCount: images.length,
    altMissing,
    altEmpty,
    preview: images.slice(0, 10)
  };
};
