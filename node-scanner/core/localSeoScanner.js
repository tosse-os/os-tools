const puppeteer = require('puppeteer')
const fs = require('fs')
const path = require('path')

const options = JSON.parse(process.argv[2])
const reportId = process.argv[3]

const resultDir = path.resolve(__dirname, '..', '..', 'storage', 'scans', reportId)
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

function normalizeText(input) {
  if (!input) return ''

  return String(input)
    .normalize('NFC')
    .toLowerCase()
    .replace(/ä/g, 'ae')
    .replace(/ö/g, 'oe')
    .replace(/ü/g, 'ue')
    .replace(/ß/g, 'ss')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, ' ')
    .trim()
}

function escapeRegExp(text) {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function createWordBoundaryRegex(term) {
  const escapedTerm = escapeRegExp(term)
  return new RegExp(`(^|[^a-z0-9])${escapedTerm}([^a-z0-9]|$)`, 'i')
}

function buildCityVariants(cityInput) {
  const normalizedCity = normalizeText(cityInput)
  const variants = new Set([normalizedCity])

  variants.add(
    normalizedCity
      .replace(/ae/g, 'a')
      .replace(/oe/g, 'o')
      .replace(/ue/g, 'u')
  )

  return Array.from(variants).filter(Boolean)
}

function hasTermWithBoundaries(text, term) {
  if (!term) return false
  return createWordBoundaryRegex(term).test(text)
}

function hasCityMatch(text, cityVariants) {
  return cityVariants.some(cityVariant => hasTermWithBoundaries(text, cityVariant))
}

function buildTitleLocalPatterns(keyword, cityVariants) {
  const escapedKeyword = escapeRegExp(keyword)

  return cityVariants.map(city => {
    const escapedCity = escapeRegExp(city)

    return {
      serviceCity: new RegExp(`(^|[^a-z0-9])${escapedKeyword}(?:\s+[a-z0-9\-]+){0,2}\s+(?:in\s+)?${escapedCity}([^a-z0-9]|$)`, 'i'),
      cityKeyword: new RegExp(`(^|[^a-z0-9])${escapedCity}\s+(?:[a-z0-9\-]+\s+){0,2}${escapedKeyword}([^a-z0-9]|$)`, 'i')
    }
  })
}

function hasTitlePattern(text, patternSets, patternType) {
  return patternSets.some(patternSet => patternSet[patternType].test(text))
}

function buildLocalIntentPatterns(keyword, cityVariants) {
  const escapedKeyword = escapeRegExp(keyword)

  return cityVariants.map(city => {
    const escapedCity = escapeRegExp(city)

    return [
      new RegExp(`(^|[^a-z0-9])${escapedKeyword}(?:\s+[a-z0-9\-]+){0,2}\s+(?:in\s+)?${escapedCity}([^a-z0-9]|$)`, 'i'),
      new RegExp(`(^|[^a-z0-9])(?:ihr(?:e|er|en)?\s+)?${escapedKeyword}(?:\s+[a-z0-9\-]+){0,2}\s+(?:in\s+)?${escapedCity}([^a-z0-9]|$)`, 'i')
    ]
  }).flat()
}

function hasPatternMatch(text, patterns) {
  return patterns.some(pattern => pattern.test(text))
}

function toSchemaItems(value) {
  if (!value) return []

  if (Array.isArray(value)) {
    return value.flatMap(item => toSchemaItems(item))
  }

  if (value['@graph'] && Array.isArray(value['@graph'])) {
    return value['@graph'].flatMap(item => toSchemaItems(item))
  }

  return [value]
}

function extractLocalEntities(normalizedText) {
  const postalCodeRegex = /\b\d{5}\b/g
  const streetRegex = /\b[a-zäöü][a-zäöüß\-]{2,}\s+\d{1,4}[a-z]?\b/gi
  const zipCityRegex = /\b(\d{5})\s+([a-zäöü][a-zäöüß\-]+(?:\s+[a-zäöü][a-zäöüß\-]+)?)\b/gi

  const postalCodes = Array.from(new Set(normalizedText.match(postalCodeRegex) || []))
  const streets = Array.from(new Set((normalizedText.match(streetRegex) || []).map(match => normalizeText(match))))

  const cities = new Set()
  let zipCityMatch

  while ((zipCityMatch = zipCityRegex.exec(normalizedText)) !== null) {
    cities.add(normalizeText(zipCityMatch[2]))
  }

  return {
    postalCodes,
    streets,
    cities: Array.from(cities)
  }
}

function countTermMentions(text, term) {
  if (!term) return 0
  const escapedTerm = escapeRegExp(term)
  const regex = new RegExp(`(^|[^a-z0-9])${escapedTerm}([^a-z0-9]|$)`, 'gi')
  let count = 0

  while (regex.exec(text) !== null) {
    count += 1
  }

  return count
}

function countCityMentionsTotal(text, cities) {
  return cities.reduce((total, city) => total + countTermMentions(text, city), 0)
}

function extractHostname(urlString) {
  try {
    return new URL(urlString).hostname.toLowerCase()
  } catch {
    return ''
  }
}

function hasGoogleBusinessLink(urls) {
  const patterns = [
    /google\.com\/maps/i,
    /goo\.gl\/maps/i,
    /g\.page/i,
    /google\.com\/search\?q=/i
  ]

  return urls.some(url => patterns.some(pattern => pattern.test(url)))
}

function detectCitationDomains(urls) {
  const citationDomains = ['yelp.', '11880.', 'gelbeseiten.', 'kennstdueinen.', 'golocal.', 'meinestadt.']

  const found = new Set()
  urls.forEach(url => {
    const host = extractHostname(url)
    citationDomains.forEach(domain => {
      if (host.includes(domain)) {
        found.add(domain.replace(/\.$/, ''))
      }
    })
  })

  return Array.from(found)
}

function detectTrustSignals(text) {
  const trustPatterns = [
    /meisterbetrieb/,
    /seit\s+19\d{2}/,
    /ueber\s+\d{1,2}\s+jahre\s+erfahrung/,
    /zertifiziert/,
    /mitglied\s+der\s+handwerkskammer/
  ]

  return trustPatterns.some(pattern => pattern.test(text))
}

function detectServiceAreaSignal(text, cityVariants) {
  const genericPatterns = [/im\s+raum\s+/, /in\s+und\s+um\s+/, /und\s+umgebung/, /im\s+grossraum\s+/]
  const cityScopedPatterns = cityVariants.map(city => {
    const escapedCity = escapeRegExp(city)
    return [
      new RegExp(`im\\s+raum\\s+${escapedCity}`, 'i'),
      new RegExp(`in\\s+und\\s+um\\s+${escapedCity}`, 'i'),
      new RegExp(`${escapedCity}\\s+und\\s+umgebung`, 'i'),
      new RegExp(`im\\s+grossraum\\s+${escapedCity}`, 'i')
    ]
  }).flat()

  return cityScopedPatterns.some(pattern => pattern.test(text)) || genericPatterns.some(pattern => pattern.test(text))
}

function detectReviewSignal(normalizedBodyText, rawBodyText) {
  const normalizedPatterns = [/google\s+bewertung/, /kundenmeinungen/, /bewertungen/]
  const hasNormalizedPattern = normalizedPatterns.some(pattern => pattern.test(normalizedBodyText))
  const hasStars = /★{3,}|⭐{3,}/.test(rawBodyText)
  return hasNormalizedPattern || hasStars
}

function findContentZipCity(normalizedBodyText) {
  const zipCityRegex = /\b(\d{5})\s+([a-z][a-z\-]+(?:\s+[a-z][a-z\-]+)?)\b/gi
  const found = []
  let match

  while ((match = zipCityRegex.exec(normalizedBodyText)) !== null) {
    found.push({
      zip: match[1],
      city: normalizeText(match[2])
    })
  }

  return found
}

function countCityMentions(normalizedText, cities) {
  const detected = new Set()

  cities.forEach(city => {
    if (hasTermWithBoundaries(normalizedText, city)) {
      detected.add(city)
    }
  })

  return detected.size
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
  const bodyText = await page.evaluate(() => {
    const clone = document.body.cloneNode(true)
    clone.querySelectorAll('script, style, noscript, template').forEach(el => el.remove())
    return clone.innerText || ''
  })

  const jsonLd = await page.$$eval(
    'script[type="application/ld+json"]',
    scripts => scripts.map(s => s.innerText)
  )

  const pageLinks = await page.$$eval('a[href]', anchors =>
    anchors
      .map(anchor => anchor.getAttribute('href') || '')
      .filter(Boolean)
  )

  const iframeSources = await page.$$eval('iframe[src]', iframes =>
    iframes
      .map(iframe => iframe.getAttribute('src') || '')
      .filter(Boolean)
  )

  const normalizedTitle = normalizeText(title)
  const normalizedH1Text = normalizeText(h1.join(' '))
  const normalizedBodyText = normalizeText(bodyText)
  const normalizedKeyword = normalizeText(options.keyword)
  const cityVariants = buildCityVariants(options.city)
  const normalizedLinks = pageLinks.map(link => normalizeText(link))
  const normalizedIframeSources = iframeSources.map(src => normalizeText(src))

  const titlePatternSets = buildTitleLocalPatterns(normalizedKeyword, cityVariants)
  const h1LocalIntentPatterns = buildLocalIntentPatterns(normalizedKeyword, cityVariants)

  let score = 0
  let priorities = []
  const breakdown = {}

  // ================= TITLE =================

  const titleResult = evaluateModule(
    'title',
    {
      keyword_present: hasTermWithBoundaries(normalizedTitle, normalizedKeyword),
      city_present: hasCityMatch(normalizedTitle, cityVariants),
      keyword_city_combination:
        cityVariants.some(cityVariant => normalizedTitle.includes(`${normalizedKeyword} ${cityVariant}`)) ||
        cityVariants.some(cityVariant => normalizedTitle.includes(`${cityVariant} ${normalizedKeyword}`)),
      service_city_pattern: hasTitlePattern(normalizedTitle, titlePatternSets, 'serviceCity'),
      city_keyword_pattern: hasTitlePattern(normalizedTitle, titlePatternSets, 'cityKeyword')
    },
    {
      keyword_present: 7,
      city_present: 6,
      keyword_city_combination: 4,
      service_city_pattern: 4,
      city_keyword_pattern: 4
    },
    {
      keyword_present: 'Keyword fehlt im Title',
      city_present: 'Stadt fehlt im Title',
      keyword_city_combination: 'Keyword + Stadt Kombination fehlt',
      service_city_pattern: 'Service + Stadt Muster fehlt im Title',
      city_keyword_pattern: 'Stadt + Service Muster fehlt im Title'
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

  const h1Result = evaluateModule(
    'h1',
    {
      exists: h1.length > 0,
      keyword_present: hasTermWithBoundaries(normalizedH1Text, normalizedKeyword),
      city_present: hasCityMatch(normalizedH1Text, cityVariants),
      local_intent_present: hasPatternMatch(normalizedH1Text, h1LocalIntentPatterns)
    },
    {
      exists: 5,
      keyword_present: 6,
      city_present: 4,
      local_intent_present: 5
    },
    {
      exists: 'Keine H1 vorhanden',
      keyword_present: 'Keyword nicht in H1 enthalten',
      city_present: 'Stadt nicht in H1 enthalten',
      local_intent_present: 'Lokale Suchintention fehlt in H1'
    },
    {
      exists: { severity: 'high', category: 'headings' },
      local_intent_present: { severity: 'medium', category: 'headings' }
    }
  )

  breakdown.h1 = h1Result
  score += h1Result.score
  priorities = priorities.concat(h1Result.priorities)

  // ================= SCHEMA =================

  let hasLocalBusiness = false
  let hasAddress = false
  let hasPhone = false
  let hasGeo = false
  let hasAreaServed = false
  let hasOpeningHours = false
  let hasSameAs = false
  let schemaCityDetected = false
  let schemaPostalCode = ''
  let schemaCity = ''
  let hasGeoCoordinates = false

  jsonLd.forEach(block => {
    try {
      const parsed = JSON.parse(block)
      const data = toSchemaItems(parsed)

      data.forEach(item => {
        const typeValue = normalizeText(item['@type'] || '')

        if (typeValue.includes('localbusiness')) {
          hasLocalBusiness = true
        }

        if (item.address) {
          hasAddress = true
          const addressText = normalizeText(JSON.stringify(item.address))
          if (hasCityMatch(addressText, cityVariants)) {
            schemaCityDetected = true
          }

          if (typeof item.address === 'object') {
            const schemaAddressLocality = normalizeText(item.address.addressLocality || item.address.addressRegion || '')
            const schemaAddressPostal = normalizeText(item.address.postalCode || '')
            if (schemaAddressLocality) {
              schemaCity = schemaAddressLocality
            }
            if (schemaAddressPostal) {
              schemaPostalCode = schemaAddressPostal
            }
          }
        }

        if (item.telephone) {
          hasPhone = true
        }

        if (item.geo) {
          hasGeo = true
          const latitude = item.geo.latitude || item.geo.lat
          const longitude = item.geo.longitude || item.geo.lng
          if (latitude !== undefined && longitude !== undefined) {
            hasGeoCoordinates = true
          }
        }

        if (item.areaServed) {
          hasAreaServed = true
        }

        if (item.openingHours || item.openingHoursSpecification) {
          hasOpeningHours = true
        }

        if (item.sameAs) {
          hasSameAs = true
        }

        const itemText = normalizeText(JSON.stringify(item))
        if (hasCityMatch(itemText, cityVariants)) {
          schemaCityDetected = true
        }
      })
    } catch {
      // ignore malformed schema blocks
    }
  })

  const schemaResult = evaluateModule(
    'schema',
    {
      has_localbusiness: hasLocalBusiness,
      has_address: hasAddress,
      has_phone: hasPhone,
      has_geo: hasGeo,
      has_areaServed: hasAreaServed,
      has_openingHours: hasOpeningHours,
      has_sameAs: hasSameAs
    },
    {
      has_localbusiness: 9,
      has_address: 4,
      has_phone: 3,
      has_geo: 3,
      has_areaServed: 2,
      has_openingHours: 2,
      has_sameAs: 2
    },
    {
      has_localbusiness: 'LocalBusiness Schema fehlt',
      has_address: 'Adresse fehlt im Schema',
      has_phone: 'Telefonnummer fehlt im Schema',
      has_geo: 'Geo-Koordinaten fehlen im Schema',
      has_areaServed: 'Servicegebiet fehlt im Schema',
      has_openingHours: 'Öffnungszeiten fehlen im Schema',
      has_sameAs: 'sameAs Profile fehlen im Schema'
    },
    {
      has_localbusiness: { severity: 'high', category: 'schema' },
      has_phone: { severity: 'medium', category: 'schema' },
      has_geo: { severity: 'medium', category: 'schema' }
    }
  )

  breakdown.schema = schemaResult
  score += schemaResult.score
  priorities = priorities.concat(schemaResult.priorities)

  // ================= NAP =================

  const germanPhoneRegex = /(?:\+49|0)\s*\(?\d{2,5}\)?(?:[\s\-\/]*\d{2,}){2,}/
  const streetAddressRegex = /\b[a-zäöü][a-zäöüß\-]{2,}\s+\d{1,4}[a-z]?\b/gi
  const zipCityRegex = /\b\d{5}\s+[a-zäöü][a-zäöüß\-]+(?:\s+[a-zäöü][a-zäöüß\-]+)?\b/gi

  const localEntities = extractLocalEntities(normalizedBodyText)
  const contentZipCities = findContentZipCity(normalizedBodyText)
  const zipMatches = normalizedBodyText.match(zipCityRegex) || []

  const hasPhoneInHtml = germanPhoneRegex.test(normalizedBodyText)
  const hasCityInHtml = hasCityMatch(normalizedBodyText, cityVariants)
  const hasStreetInHtml = (normalizedBodyText.match(streetAddressRegex) || []).length > 0
  const hasZipInHtml = localEntities.postalCodes.length > 0
  const hasAddressInHtml = hasStreetInHtml && zipMatches.length > 0

  const hasGoogleBusinessProfileLink = hasGoogleBusinessLink(normalizedLinks)
  const citationLinksFound = detectCitationDomains(normalizedLinks)
  const trustSignalsDetected = detectTrustSignals(normalizedBodyText)
  const serviceAreaSignal = detectServiceAreaSignal(normalizedBodyText, cityVariants)
  const localIntentPatterns = buildLocalIntentPatterns(normalizedKeyword, cityVariants)
  const localKeywordCoverage = hasPatternMatch(normalizedBodyText, localIntentPatterns)
  const geoCoordinatesPresent = hasGeoCoordinates
  const hasGoogleMapEmbed = normalizedIframeSources.some(src => src.includes('google.com/maps/embed'))
  const contactPageDetected = normalizedLinks.some(link => /\/kontakt|\/contact|\/anfahrt|\/impressum/i.test(link))
  const reviewSignalDetected = detectReviewSignal(normalizedBodyText, bodyText)

  const napResult = evaluateModule(
    'nap',
    {
      phone_present: hasPhoneInHtml,
      city_present: hasCityInHtml,
      street_present: hasStreetInHtml,
      zip_present: hasZipInHtml,
      address_present: hasAddressInHtml
    },
    {
      phone_present: 5,
      city_present: 5,
      street_present: 4,
      zip_present: 3,
      address_present: 3
    },
    {
      phone_present: 'Telefonnummer nicht im sichtbaren Inhalt gefunden',
      city_present: 'Stadt nicht im Content gefunden',
      street_present: 'Straße und Hausnummer nicht gefunden',
      zip_present: 'Postleitzahl nicht gefunden',
      address_present: 'Vollständige Adresse nicht im Content gefunden'
    },
    {
      phone_present: { severity: 'medium', category: 'nap' },
      address_present: { severity: 'high', category: 'nap' }
    }
  )

  breakdown.nap = napResult
  score += napResult.score
  priorities = priorities.concat(napResult.priorities)

  // ================= CONTENT SIGNALS =================

  const cityMentionsPool = new Set([...cityVariants, ...localEntities.cities])
  const cityMentionsCount = countCityMentionsTotal(normalizedBodyText, Array.from(cityMentionsPool))
  const serviceAreaDetected = cityMentionsCount > 2 || serviceAreaSignal
  const wordCount = normalizedBodyText.split(/\s+/).filter(Boolean).length || 1
  const entityDensityScore = Number(Math.min(10, (cityMentionsCount / wordCount) * 1000).toFixed(2))

  const contentZipCityMatch = contentZipCities.find(entry => hasCityMatch(entry.city, cityVariants))
  const addressConsistency = Boolean(
    schemaCity
    && contentZipCityMatch
    && hasCityMatch(schemaCity, cityVariants)
    && hasCityMatch(contentZipCityMatch.city, cityVariants)
    && (!schemaPostalCode || schemaPostalCode === contentZipCityMatch.zip)
  )

  const contentResult = evaluateModule(
    'content',
    {
      city_present: hasCityInHtml,
      service_area_detected: serviceAreaDetected,
      entities_detected: localEntities.postalCodes.length > 0 || localEntities.streets.length > 0,
      has_google_business_link: hasGoogleBusinessProfileLink,
      citation_links_found: citationLinksFound.length > 0,
      trust_signals_detected: trustSignalsDetected,
      service_area_signal: serviceAreaSignal,
      local_keyword_coverage: localKeywordCoverage,
      address_consistency: addressConsistency,
      geo_coordinates_present: geoCoordinatesPresent,
      has_google_map_embed: hasGoogleMapEmbed,
      contact_page_detected: contactPageDetected,
      review_signal_detected: reviewSignalDetected
    },
    {
      city_present: 1.5,
      service_area_detected: 0.5,
      entities_detected: 0.5,
      has_google_business_link: 1,
      citation_links_found: 0.75,
      trust_signals_detected: 0.75,
      service_area_signal: 0.75,
      local_keyword_coverage: 1,
      address_consistency: 1,
      geo_coordinates_present: 0.75,
      has_google_map_embed: 0.5,
      contact_page_detected: 0.5,
      review_signal_detected: 0.5
    },
    {
      city_present: 'Stadt wird im Hauptcontent nicht erwähnt',
      service_area_detected: 'Servicegebiet mit mehreren Städten wurde nicht erkannt',
      entities_detected: 'Lokale Entitäten (PLZ/Straße) wurden nicht erkannt',
      has_google_business_link: 'Google Business Profil-Link fehlt',
      citation_links_found: 'Lokale Citation-Links wurden nicht gefunden',
      trust_signals_detected: 'Trust-Signale (E-E-A-T) wurden nicht erkannt',
      service_area_signal: 'Servicegebiet-Formulierungen fehlen',
      local_keyword_coverage: 'Lokale Keyword-Kombinationen (Service + Stadt) fehlen',
      address_consistency: 'Adressdaten zwischen Schema und Inhalt sind inkonsistent',
      geo_coordinates_present: 'Geo-Koordinaten im Schema fehlen',
      has_google_map_embed: 'Google Maps Embed wurde nicht gefunden',
      contact_page_detected: 'Kontakt-/Anfahrt-/Impressum-Seite wurde nicht erkannt',
      review_signal_detected: 'Bewertungs-Signale wurden nicht erkannt'
    },
    {
      has_google_business_link: { severity: 'high', category: 'local_signals' },
      address_consistency: { severity: 'high', category: 'consistency' },
      geo_coordinates_present: { severity: 'medium', category: 'schema' },
      has_google_map_embed: { severity: 'low', category: 'local_signals' },
      contact_page_detected: { severity: 'medium', category: 'local_signals' }
    }
  )

  contentResult.city_mentions_count = cityMentionsCount
  contentResult.city_mentions = cityMentionsCount
  contentResult.entity_density_score = entityDensityScore
  contentResult.citation_count = citationLinksFound.length
  contentResult.citation_links_found = citationLinksFound
  contentResult.trust_signals_detected = trustSignalsDetected
  breakdown.content = contentResult
  score += contentResult.score

  // ================= CONSISTENCY =================

  const titleCityDetected = hasCityMatch(normalizedTitle, cityVariants)
  const h1CityDetected = hasCityMatch(normalizedH1Text, cityVariants)
  const bodyCityDetected = hasCityInHtml
  const addressCityDetected = Boolean(contentZipCityMatch)
  const locationConsistency = titleCityDetected && h1CityDetected && bodyCityDetected && schemaCityDetected && addressCityDetected

  breakdown.consistency = {
    location_consistency: locationConsistency,
    title_city: titleCityDetected,
    h1_city: h1CityDetected,
    body_city: bodyCityDetected,
    schema_city: schemaCityDetected,
    address_city: addressCityDetected
  }

  if (!h1Result.checks.local_intent_present) {
    priorities.push({
      code: 'missing_local_intent',
      severity: 'medium',
      category: 'headings',
      message: 'Lokale Suchintention ist in der H1 nicht klar erkennbar'
    })
  }

  if (!napResult.checks.address_present) {
    priorities.push({
      code: 'missing_address',
      severity: 'high',
      category: 'nap',
      message: 'Vollständige Adresse (Straße + PLZ/Ort) fehlt im sichtbaren Inhalt'
    })
  }

  if (!contentResult.checks.has_google_business_link) {
    priorities.push({
      code: 'missing_google_business_link',
      severity: 'high',
      category: 'local_signals',
      message: 'Es wurde kein Google Business Profile-Link erkannt'
    })
  }

  if (!contentResult.checks.city_present) {
    priorities.push({
      code: 'missing_city_signal',
      severity: 'high',
      category: 'content',
      message: 'Die Zielstadt wird im Seiteninhalt nicht ausreichend erwähnt'
    })
  }

  if (!contentResult.checks.geo_coordinates_present) {
    priorities.push({
      code: 'missing_geo_coordinates',
      severity: 'medium',
      category: 'schema',
      message: 'Geo-Koordinaten wurden im Schema nicht erkannt'
    })
  }

  if (!contentResult.checks.has_google_map_embed) {
    priorities.push({
      code: 'missing_map_embed',
      severity: 'low',
      category: 'local_signals',
      message: 'Ein eingebetteter Google-Maps-Standort fehlt'
    })
  }

  if (!contentResult.checks.contact_page_detected) {
    priorities.push({
      code: 'missing_contact_page',
      severity: 'medium',
      category: 'local_signals',
      message: 'Kontakt-, Anfahrt- oder Impressum-Link wurde nicht gefunden'
    })
  }

  if (!locationConsistency) {
    priorities.push({
      code: 'location_consistency_warning',
      severity: 'medium',
      category: 'consistency',
      message: 'Lokale Signale sind inkonsistent zwischen Title, H1, Content und Schema'
    })
  }

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
