import express from 'express'
import fs from 'node:fs'
import path from 'node:path'
import { config } from './config.js'
import { sendMail } from './zepto.js'
import { loadLeads, buildReplyTo } from './leads.js'
import { renderTemplate } from './templates.js'
import { quota, alreadySent, markSent, markSkipped, currentDailyLimit, todayKey } from './limiter.js'

const app = express()
app.use(express.json())

const appName = 'cold-email-service'

function loadTemplate(name) {
  const resolved = path.resolve(process.cwd(), `templates/${name}.json`)
  return JSON.parse(fs.readFileSync(resolved, 'utf8'))
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
    const { email, name = '', website = '', city = '', category = '', notes = '', template = 'cold', campaign = 'batch1' } = req.body || {}

    if (!email) return res.status(400).json({ error: 'email is required' })

    const q = quota()
    if (q.remaining <= 0) {
      return res.status(429).json({ error: 'Daily quota exhausted', quota: q })
    }

    if (alreadySent(email)) {
      return res.status(200).json({ skipped: true, reason: 'already-sent', email })
    }

    const lead = { name, email, website, city, category, notes }
    const tpl = loadTemplate(template)
    const rendered = renderTemplate(tpl, lead, campaign)
    const replyTo = buildReplyTo()

    const result = await sendMail({
      to: email,
      subject: rendered.subject,
      textbody: rendered.textbody,
      htmlbody: rendered.htmlbody,
      replyTo,
      tags: [campaign],
    })

    markSent(email)
    res.json({ sent: true, email, result, quota: quota() })
  } catch (e) {
    res.status(500).json({ error: e.message })
  }
})

// Run a campaign over the whole CSV
// body: { template?, campaign?, limit? } — limit overrides daily quota for this run
app.post('/campaign', async (req, res) => {
  try {
    const { template = 'cold', campaign = 'batch1', limit } = req.body || {}
    const leads = loadLeads()

    const cap = typeof limit === 'number' ? limit : quota().remaining
    const results = { sent: 0, skipped: 0, failed: 0, errors: [], dryRun: config.dryRun }

    for (const lead of leads) {
      if (results.sent >= cap) {
        results.errors.push('Daily quota reached — run again tomorrow')
        break
      }
      if (alreadySent(lead.email)) {
        results.skipped++
        continue
      }

      try {
        const tpl = loadTemplate(template)
        const rendered = renderTemplate(tpl, lead, campaign)
        await sendMail({
          to: lead.email,
          subject: rendered.subject,
          textbody: rendered.textbody,
          htmlbody: rendered.htmlbody,
          replyTo: buildReplyTo(),
          tags: [campaign],
        })
        markSent(lead.email)
        results.sent++
      } catch (e) {
        results.failed++
        markSkipped(lead.email) // don't retry failing addresses in this wave
        results.errors.push(`${lead.email}: ${e.message}`)
      }
    }

    res.json({ data: results, quota: quota() })
  } catch (e) {
    res.status(500).json({ error: e.message })
  }
})

// Mark an email as replied (so it can be moved to a "warm" list / follow-up campaign)
// body: { email }
app.post('/replied', (req, res) => {
  const { email } = req.body || {}
  if (!email) return res.status(400).json({ error: 'email is required' })
  markSent(email) // dedupe against future cold waves
  res.json({ ok: true, email })
})

app.listen(config.port, () => {
  console.log(`[${appName}] listening on :${config.port} (dryRun=${config.dryRun}, dailyLimit=${currentDailyLimit()})`)
})
