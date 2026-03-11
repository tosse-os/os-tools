const { URL } = require('url');

const TRACKING_QUERY_PARAM_PATTERNS = [
  /^utm_/i,
  /^fbclid$/i,
  /^gclid$/i,
  /^msclkid$/i,
  /^mc_eid$/i,
  /^mc_cid$/i,
  /^igshid$/i,
  /^ref$/i,
  /^source$/i,
];

function isTrackingParam(key) {
  return TRACKING_QUERY_PARAM_PATTERNS.some((pattern) => pattern.test(key));
}

function normalizeUrl(raw, base = '') {
  try {
    const candidate = String(raw || '').trim();
    if (!candidate) {
      return null;
    }

    const url = new URL(candidate, base);

    url.hash = '';

    for (const key of Array.from(url.searchParams.keys())) {
      if (isTrackingParam(key)) {
        url.searchParams.delete(key);
      }
    }

    const orderedEntries = Array.from(url.searchParams.entries()).sort(([a], [b]) => a.localeCompare(b));
    url.search = '';
    for (const [key, value] of orderedEntries) {
      url.searchParams.append(key, value);
    }

    if (url.pathname.length > 1) {
      url.pathname = url.pathname.replace(/\/+$/g, '') || '/';
    }

    if ((url.protocol === 'http:' && url.port === '80') || (url.protocol === 'https:' && url.port === '443')) {
      url.port = '';
    }

    return url.href.replace(/\/$/, '');
  } catch {
    return null;
  }
}

function collectUniqueUrls(baseUrl, hrefs) {
  const seen = new Set();
  const uniqueUrls = [];
  const origin = new URL(baseUrl).origin;

  const add = (candidate) => {
    const normalized = normalizeUrl(candidate, baseUrl);

    if (!normalized) {
      return;
    }

    if (!normalized.startsWith(origin)) {
      return;
    }

    if (seen.has(normalized)) {
      return;
    }

    seen.add(normalized);
    uniqueUrls.push(normalized);
  };

  add(baseUrl);

  for (const href of hrefs) {
    add(href);
  }

  return uniqueUrls;
}

module.exports = {
  normalizeUrl,
  collectUniqueUrls,
};
