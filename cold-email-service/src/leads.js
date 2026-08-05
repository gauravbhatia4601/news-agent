import fs from 'node:fs'
import { parse } from 'csv-parse/sync'
import { config } from './config.js'

/**
 * Loads leads from a CSV file.
 * Expected columns: name,email,website,city,category,notes,source_url
 * Skips rows without a valid email.
 */
export function loadLeads(csvPath = config.leadsCsv) {
  if (!fs.existsSync(csvPath)) {
    throw new Error(`Leads CSV not found: ${csvPath}`)
  }

  const raw = fs.readFileSync(csvPath, 'utf8')
  const rows = parse(raw, {
    columns: true,
    skip_empty_lines: true,
    trim: true,
  })

  return rows
    .filter((r) => r.email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(r.email))
    .map((r) => ({
      name: r.name || '',
      email: r.email.trim().toLowerCase(),
      website: r.website || '',
      city: r.city || '',
      category: r.category || '',
      notes: r.notes || '',
      sourceUrl: r.source_url || '',
    }))
}

export function buildReplyTo() {
  if (!config.replyTagEnabled) return undefined
  const [user, domain] = config.senderEmail.split('@')
  if (!domain) return undefined
  // replies route to sender+outreach@domain — enable catch-all or alias in your mail provider
  return { address: `${user}+outreach@${domain}`, name: config.senderName }
}
