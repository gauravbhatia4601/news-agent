import { test } from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

// Global DRY_RUN=true so sendMail returns a mock (sends "succeed" without API key).
// Per-request dryRun=false so markSent IS called, letting us read template assignments
// from sent.json after the campaign.
delete process.env.OUTREACH_API_KEY
delete process.env.WEBHOOK_SECRET
process.env.DRY_RUN = 'true'
const dataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'ces-ab-'))
process.env.DATA_DIR = dataDir

// 10-lead CSV so we can verify A/B split across a known population.
const csvDir = fs.mkdtempSync(path.join(os.tmpdir(), 'ces-abcsv-'))
const csvPath = path.join(csvDir, 'leads.csv')
const rows = ['name,email,website,city,category,notes']
for (let i = 0; i < 10; i++) rows.push(`Lead${i},lead${i}@example.com,,,,`)
fs.writeFileSync(csvPath, rows.join('\n'))
process.env.LEADS_CSV = csvPath

const { markSent } = await import('../src/limiter.js')
const { app } = await import('../src/index.js')

const sentFile = path.join(dataDir, 'sent.json')

async function withServer(fn) {
  const server = app.listen(0)
  const base = `http://localhost:${server.address().port}`
  try {
    await fn(base)
  } finally {
    server.close()
  }
}

test('ab-split: already-sent leads do not skew template distribution', async () => {
  // Pre-mark leads 1-3 as sent (they'll be skipped by alreadySent)
  for (let i = 1; i <= 3; i++) {
    markSent(`lead${i}@example.com`, { campaign: 'prior', template: 'cold' })
  }

  await withServer(async (base) => {
    const res = await fetch(`${base}/campaign`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({
        campaign: 'batch1',
        templates: ['cold', 'cold-pain'],
        weights: [1, 1],
        limit: 10,
        dryRun: false,
      }),
    })
    const body = await res.json()

    // 7 leads sent (10 - 3 pre-sent), 3 skipped
    assert.equal(body.data.sent, 7, `expected 7 sends, got ${body.data.sent}`)
    assert.equal(body.data.skipped, 3, `expected 3 skipped, got ${body.data.skipped}`)

    // Read template assignments from sent.json — only batch1 entries (not prior)
    const state = JSON.parse(fs.readFileSync(sentFile, 'utf8'))
    const templates = []
    for (let i = 0; i < 10; i++) {
      const meta = state.sent[`lead${i}@example.com`]
      if (meta && meta.campaign === 'batch1') templates.push(meta.template)
    }

    // 7 sends across 2 templates: must split 4/3 or 3/4
    const coldCount = templates.filter((t) => t === 'cold').length
    const painCount = templates.filter((t) => t === 'cold-pain').length
    assert.equal(coldCount + painCount, 7, 'all 7 sends should have a template assigned')
    assert.ok(
      Math.abs(coldCount - painCount) <= 1,
      `split should be even (4/3 or 3/4), got cold=${coldCount}, cold-pain=${painCount}`,
    )
  })
})

process.on('exit', () => {
  fs.rmSync(dataDir, { recursive: true, force: true })
  fs.rmSync(csvDir, { recursive: true, force: true })
})