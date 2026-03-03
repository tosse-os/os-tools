const { URL } = require('url');

function normalizeUrl(raw, base = '') {
  try {
    const url = new URL(raw, base);
    url.hash = '';
    url.search = '';
    return decodeURIComponent(url.href).toLowerCase().replace(/\/+$/, '');
  } catch {
    return null;
  }
}

function collectUniqueUrls(baseUrl, hrefs) {
  const seen = new Map();
  const origin = new URL(baseUrl).origin;

  const add = (original) => {
    const normalized = normalizeUrl(original, baseUrl);
    if (normalized && normalized.startsWith(origin) && !seen.has(normalized)) {
      seen.set(normalized, normalizeUrl(original, baseUrl)); // könnte auch original sein
    }
  };

  add(baseUrl);

  for (const href of hrefs) {
    add(href);
  }

  return Array.from(seen.values()); // gibt eindeutige Normalformen zurück
}

module.exports = {
  normalizeUrl,
  collectUniqueUrls,
};
