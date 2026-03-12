const crypto = require('crypto');

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function decodeHtmlEntities(text) {
  return String(text || '')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;|&apos;/gi, "'")
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>');
}

function stripTags(html) {
  return decodeHtmlEntities(String(html || '').replace(/<[^>]+>/g, ' '))
    .replace(/\s+/g, ' ')
    .trim();
}

function toPositiveInt(value, fallback) {
  const num = Number(value);
  return Number.isFinite(num) && num > 0 ? Math.floor(num) : fallback;
}

function hashContent(content) {
  return crypto.createHash('sha256').update(content || '').digest('hex');
}


function isRedirectStatus(code) {
  return [301, 302, 307, 308].includes(Number(code));
}

async function resolveLinkStatus(targetUrl, normalizeUrl, maxRedirects = 6) {
  const chain = [];
  let current = targetUrl;

  for (let index = 0; index < maxRedirects; index += 1) {
    let response;
    try {
      response = await fetchWithTimeout(current, { method: 'HEAD', redirect: 'manual' });

      if ([400, 403, 405].includes(response.status)) {
        response = await fetchWithTimeout(current, { method: 'GET', redirect: 'manual' });
      }
    } catch {
      return {
        status_code: null,
        redirect_target: null,
        redirect_chain: chain,
        redirect_chain_length: chain.length,
      };
    }

    const statusCode = Number(response.status);
    const location = response.headers.get('location');
    const resolvedNext = location ? normalizeUrl(location, current) : null;

    if (isRedirectStatus(statusCode) && resolvedNext) {
      chain.push({ url: current, status_code: statusCode, target: resolvedNext });
      current = resolvedNext;
      continue;
    }

    return {
      status_code: statusCode,
      redirect_target: chain.length > 0 ? current : null,
      redirect_chain: chain,
      redirect_chain_length: chain.length,
    };
  }

  return {
    status_code: null,
    redirect_target: current,
    redirect_chain: chain,
    redirect_chain_length: chain.length,
  };
}

async function fetchWithTimeout(url, options = {}, timeoutMs = 10000) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  try {
    return await fetch(url, { ...options, signal: controller.signal });
  } finally {
    clearTimeout(timeout);
  }
}

module.exports = {
  sleep,
  stripTags,
  decodeHtmlEntities,
  toPositiveInt,
  hashContent,
  fetchWithTimeout,
  resolveLinkStatus,
};
