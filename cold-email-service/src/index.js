import express from 'express'
import fs from 'node:fs'
import path from 'node:path'
import { config } from './config.js'
import { sendMail, isPermanentError } from './zepto.js'
import { loadLeads, buildReplyTo } from './leads.js'
import { renderTemplate } from './templates.js'
import {
  quota,
  alreadySent,
  sentStats,
  sentMeta,
  followupDue,
  markSent,
  markSkipped,
  markBounced,
  markReplied,
  markFollowupSent,
  currentDailyLimit,
  todayKey,
} from './limiter.js'

const app = express()
app.use(express.json())

const appName = 'cold-email-service'
const eventsFile = path.join(config.dataDir, 'events.json')

function recordEvent(event) {
  fs.mkdirSync(config.dataDir, { recursive: true })
  let events = []
  try { events = JSON.parse(fs.readFileSync(eventsFile, 'utf8')) } catch {}
  events.push({ at: new Date().toISOString(), ...event })
  fs.writeFileSync(eventsFile, JSON.stringify(events, null, 2))
  if (event.event === 'bounced') {
    if (event.email) markBounced(event.email)
  } else if (event.event === 'spam_complaint') {
    if (event.email) markSkipped(event.email, 'spam complaint')
  }
}

function loadTemplate(name) {
  const resolved = path.resolve(process.cwd(), `templates/${name}.json`)
  if (!fs.existsSync(resolved)) throw new Error(`Template not found: ${name}`)
  return JSON.parse(fs.readFileSync(resolved, 'utf8'))
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms))

async function sendOne({ lead, template, campaign, dryRun }) {
  const tpl = loadTemplate(template)
  const rendered = renderTemplate(tpl, lead, campaign)
  return sendMail({
    to: lead.email,
    subject: rendered.subject,
    textbody: rendered.textbody,
    htmlbody: rendered.htmlbody,
    replyTo: buildReplyTo(),
    tags: [campaign, template],
  })
}

/**
 * Normalize A/B template list.
 * Accepts either `template` (single) or `templates` (array) with optional
 * `weights` (array of numbers). Leads are assigned round-robin across the
 * weighted list.
 */
function resolveTemplates({ template, templates, weights }) {
  const list = templates?.length
    ? templates.map((t) => ({ name: t, weight: 1 }))
    : [{ name: template || 'cold', weight: 1 }]
  if (weights?.length) {
    list.forEach((t, i) => { t.weight = weights[i] || 1 })
  }
  for (const t of list) loadTemplate(t.name) // validate early
  const expanded = list.flatMap((t) => Array(Math.max(1, t.weight)).fill(t.name))
  return { names: list.map((t) => t.name), expanded }
}

async function runCampaign({ campaign, templates, template, weights, limit, dryRun }) {
  const leads = loadLeads()
  const { names, expanded } = resolveTemplates({ template, templates, weights })

  const cap = typeof limit === 'number' ? Math.min(limit, leads.length) : quota().remaining
  const results = { sent: 0, skipped: 0, failed: 0, permanentSkipped: 0, errors: [], dryRun: Boolean(dryRun), templates: names }

  let sentCount = 0
  for (const lead of leads) {
    if (sentCount >= cap) {
      results.errors.push('Daily quota reached — run again tomorrow')
      break
    }
    if (alreadySent(lead.email)) {
      results.skipped++
      continue
    }

    const tplName = expanded[sentCount % expanded.length]
    try {
      await sendOne({ lead, template: tplName, campaign, dryRun })
      markSent(lead.email, { campaign, template: tplName })
      sentCount++
      results.sent++
      if (config.interSendDelay > 0 && !dryRun) await sleep(config.interSendDelay)
    } catch (e) {
      results.failed++
      if (isPermanentError(e)) {
        results.permanentSkipped++
        markSkipped(lead.email, e.message)
      }
      results.errors.push(`${lead.email}: ${e.message}`)
    }
  }

  return { ...results, quota: quota() }
}

// ---------- Routes ----------

app.get('/health', (req, res) => {
  res.json({ status: 'ok', service: appName, time: new Date().toISOString() })
})

app.get('/quota', (req, res) => {
  res.json({ ...quota(), limitToday: currentDailyLimit(), date: todayKey(), dryRun: config.dryRun })
})

app.get('/leads', (req, res) => {
  try {
    const leads = loadLeads()
    res.json({ data: leads, count: leads.length })
  } catch (e) {
    res.status(500).json({ error: e.message })
  }
})

// Send to a single lead
// body: { email, name?, website?, city?, category?, notes?, template?, campaign? }
app.post('/send', async (req, res) => {
  try {
    const { email, name = '', website = '', city = '', category = '', notes = '', template = 'cold', campaign = 'batch1', dryRun = config.dryRun } = req.body || {}

    if (!email) return res.status(400).json({ error: 'email is required' })

    const q = quota()
    if (q.remaining <= 0) {
      return res.status(429).json({ error: 'Daily quota exhausted', quota: q })
    }

    if (alreadySent(email)) {
      return res.status(200).json({ skipped: true, reason: 'already-sent', email })
    }

    const lead = { name, email, website, city, category, notes }
    const result = await sendOne({ lead, template, campaign, dryRun })
    markSent(email, { campaign, template })

    res.json({ sent: true, email, result, quota: quota() })
  } catch (e) {
    res.status(500).json({ error: e.message, permanent: isPermanentError(e) })
  }
})

// Run a wave over the whole CSV with A/B template split
// body: {
//   template: 'cold' | templates: ['cold','cold-pain'], weights: [1,1],
//   campaign: 'batch1', limit?: number, dryRun?: bool
// }
app.post('/campaign', async (req, res) => {
  try {
    const { template, templates, weights, campaign = 'batch1', limit, dryRun } = req.body || {}
    const results = await runCampaign({ template, templates, weights, campaign, limit, dryRun })
    res.json({ data: results })
  } catch (e) {
    res.status(500).json({ error: e.message })
  }
})

// Send follow-ups to everyone sent ≥ FOLLOWUP_DAYS days ago who hasn't replied
// body: { campaign?: 'followup', template?: 'followup', limit?, dryRun? }
app.post('/campaign/followup', async (req, res) => {
  try {
    const { campaign = 'followup', template = 'followup', limit, dryRun } = req.body || {}
    const leads = loadLeads()
    const cap = typeof limit === 'number' ? limit : quota().remaining
    const results = { sent: 0, skipped: 0, failed: 0, notDue: 0, errors: [], dryRun: Boolean(dryRun), template }

    let sentCount = 0
    for (const lead of leads) {
      if (sentCount >= cap) {
        results.errors.push('Daily quota reached — run again tomorrow')
        break
      }
      if (!followupDue(lead.email)) {
        results.notDue++
        continue
      }

      try {
        await sendOne({ lead, template, campaign, dryRun })
        markFollowupSent(lead.email, template)
        sentCount++
        results.sent++
        if (config.interSendDelay > 0 && !dryRun) await sleep(config.interSendDelay)
      } catch (e) {
        results.failed++
        results.errors.push(`${lead.email}: ${e.message}`)
      }
    }

    res.json({ data: { ...results, quota: quota() } })
  } catch (e) {
    res.status(500).json({ error: e.message })
  }
})

// Preview who is due for a follow-up right now
app.get('/followup/due', (req, res) => {
  const leads = loadLeads().filter((l) => followupDue(l.email))
  res.json({ data: leads.map((l) => ({ email: l.email, name: l.name, meta: sentMeta(l.email) })), count: leads.length })
})

// ZeptoMail webhook receiver — configure in ZeptoMail dashboard → Webhooks
// POSTs events (delivered, opened, clicked, bounced, spam_complaint) here.
app.post('/webhook/zeptomail', (req, res) => {
  try {
    const body = req.body
    const rawEvent = body?.event || body?.type || 'unknown'
    const eventMap = { open: 'opened', click: 'clicked', bounce: 'bounced', spam_complaint: 'spam_complaint', delivered: 'delivered' }
    const event = eventMap[rawEvent] || rawEvent
    const email = body?.email || body?.to?.address || body?.recipient || body?.address || ''
    recordEvent({ event, email, payload: body })
    res.json({ ok: true })
  } catch (e) {
    res.status(500).json({ error: e.message })
  }
})

// Stats for reporting — sent/replied/followups + delivery events
app.get('/stats', (req, res) => {
  let events = []
  try { events = JSON.parse(fs.readFileSync(eventsFile, 'utf8')) } catch {}
  const byEvent = events.reduce((acc, e) => {
    acc[e.event] = (acc[e.event] || 0) + 1
    return acc
  }, {})
  res.json({ data: { ...sentStats(), events: byEvent, recentEvents: events.slice(-100) } })
})

// Mark an email as replied (via webhook/manual/IMAP — removes from follow-up queue)
// body: { email }
app.post('/replied', (req, res) => {
  const { email } = req.body || {}
  if (!email) return res.status(400).json({ error: 'email is required' })
  markReplied(email)
  res.json({ ok: true, email })
})

app.listen(config.port, () => {
  console.log(`[${appName}] listening on :${config.port} (dryRun=${config.dryRun}, dailyLimit=${currentDailyLimit()})`)
})
