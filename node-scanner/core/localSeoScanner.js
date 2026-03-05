const puppeteer = require('puppeteer')
const fs = require('fs')
const path = require('path')

const options = JSON.parse(process.argv[2])
const reportId = process.argv[3]

const resultDir = path.resolve(__dirname, '..', 'storage', 'scans', reportId)
if (!fs.existsSync(resultDir)) {
  fs.mkdirSync(resultDir, { recursive: true })
}

const progressPath = path.join(resultDir, 'progress.json')

const updateProgress = (status) => {
  fs.writeFileSync(progressPath, JSON.stringify({
    current: 1,
    total: 1,
    status
  }))
}

const rateScore = (score) => {
  if (score < 50) return { level: 'critical', label: 'Kritisch', color: 'red' }
  if (score < 70) return { level: 'weak', label: 'Schwach', color: 'orange' }
  if (score < 85) return { level: 'good', label: 'Gut', color: 'blue' }
  return { level: 'excellent', label: 'Sehr gut', color: 'green' }
}

function evaluateModule(moduleKey, checks, weights, messages, priorityConfig = {}) {
  let score = 0
  const missing = []
  const priorities = []

  for (const rule in checks) {
    if (checks[rule]) {
      score += weights[rule] || 0
    } else {
      if (messages[rule]) {
        missing.push(messages[rule])
      }

      if (priorityConfig[rule]) {
        priorities.push({
          code: `${moduleKey}_${rule}`,
          severity: priorityConfig[rule].severity,
          category: priorityConfig[rule].category,
          message: messages[rule]
        })
      }
    }
  }

  const max = Object.values(weights).reduce((a, b) => a + b, 0)

  return {
    score,
    max,
    checks,
    weights,
    missing,
    priorities
  }
}

; (async () => {

  updateProgress('running')

  const browser = await puppeteer.launch({ headless: true })
  const page = await browser.newPage()

  await page.goto(options.url, { waitUntil: 'domcontentloaded', timeout: 15000 })

  const title = await page.title()
  const h1 = await page.$$eval('h1', els => els.map(e => e.innerText))
  const bodyText = await page.evaluate(() => document.body.innerText)

  const jsonLd = await page.$$eval(
    'script[type="application/ld+json"]',
    scripts => scripts.map(s => s.innerText)
  )

  const keyword = options.keyword.toLowerCase()
  const city = options.city.toLowerCase()

  let score = 0
  let priorities = []
  const breakdown = {}

  // ================= TITLE =================

  const titleLower = title.toLowerCase()

  const titleResult = evaluateModule(
    'title',
    {
      keyword_present: titleLower.includes(keyword),
      city_present: titleLower.includes(city),
      keyword_city_combination:
        titleLower.includes(`${keyword} ${city}`) ||
        titleLower.includes(`${city} ${keyword}`)
    },
    {
      keyword_present: 10,
      city_present: 10,
      keyword_city_combination: 5
    },
    {
      keyword_present: 'Keyword fehlt im Title',
      city_present: 'Stadt fehlt im Title',
      keyword_city_combination: 'Keyword + Stadt Kombination fehlt'
    },
    {
      keyword_present: { severity: 'high', category: 'title' },
      city_present: { severity: 'medium', category: 'title' }
    }
  )

  breakdown.title = titleResult
  score += titleResult.score
  priorities = priorities.concat(titleResult.priorities)

  // ================= H1 =================

  const h1Text = h1.join(' ').toLowerCase()

  const h1Result = evaluateModule(
    'h1',
    {
      exists: h1.length > 0,
      keyword_present: h1Text.includes(keyword),
      city_present: h1Text.includes(city)
    },
    {
      exists: 5,
      keyword_present: 8,
      city_present: 7
    },
    {
      exists: 'Keine H1 vorhanden',
      keyword_present: 'Keyword nicht in H1 enthalten',
      city_present: 'Stadt nicht in H1 enthalten'
    },
    {
      exists: { severity: 'high', category: 'headings' }
    }
  )

  breakdown.h1 = h1Result
  score += h1Result.score
  priorities = priorities.concat(h1Result.priorities)

  // ================= SCHEMA =================

  let hasLocalBusiness = false
  let hasAddress = false
  let hasPhone = false

  jsonLd.forEach(block => {
    try {
      const parsed = JSON.parse(block)
      const data = Array.isArray(parsed) ? parsed : [parsed]

      data.forEach(item => {
        if (item['@type'] && item['@type'].toLowerCase().includes('localbusiness')) {
          hasLocalBusiness = true
          if (item.address) hasAddress = true
          if (item.telephone) hasPhone = true
        }
      })
    } catch { }
  })

  const schemaResult = evaluateModule(
    'schema',
    {
      has_localbusiness: hasLocalBusiness,
      has_address: hasAddress,
      has_phone: hasPhone
    },
    {
      has_localbusiness: 10,
      has_address: 5,
      has_phone: 5
    },
    {
      has_localbusiness: 'LocalBusiness Schema fehlt',
      has_address: 'Adresse fehlt im Schema',
      has_phone: 'Telefonnummer fehlt im Schema'
    },
    {
      has_localbusiness: { severity: 'high', category: 'schema' },
      has_phone: { severity: 'medium', category: 'schema' }
    }
  )

  breakdown.schema = schemaResult
  score += schemaResult.score
  priorities = priorities.concat(schemaResult.priorities)

  // ================= NAP =================

  const hasPhoneInHtml = bodyText.match(/\+?\d[\d\s\-\/]{6,}/)
  const hasCityInHtml = bodyText.includes(city)

  const napResult = evaluateModule(
    'nap',
    {
      phone_present: !!hasPhoneInHtml,
      city_present: hasCityInHtml
    },
    {
      phone_present: 10,
      city_present: 10
    },
    {
      phone_present: 'Telefonnummer nicht im sichtbaren Inhalt gefunden',
      city_present: 'Stadt nicht im Content gefunden'
    },
    {
      phone_present: { severity: 'medium', category: 'nap' }
    }
  )

  breakdown.nap = napResult
  score += napResult.score
  priorities = priorities.concat(napResult.priorities)

  const result = {
    dimension: 'local_seo',
    score,
    max_score: 100,
    rating: rateScore(score),
    breakdown,
    priorities
  }

  fs.writeFileSync(
    path.join(resultDir, '0.json'),
    JSON.stringify(result, null, 2)
  )

  updateProgress('done')
  await browser.close()

})()
