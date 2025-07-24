module.exports = async function statusCheck(page, url) {
  const result = {
    status: null,
    redirected: false,
    finalUrl: url,
    error: null
  };

  try {
    const response = await page.goto(url, {
      waitUntil: 'domcontentloaded',
      timeout: 20000
    });

    if (response) {
      result.status = response.status();
      result.finalUrl = response.url();
      result.redirected = result.finalUrl && result.finalUrl !== url;
    } else {
      result.status = 'unknown';
    }
  } catch (error) {
    result.status = 'error';
    result.error = error.message;
  }

  return result;
};
