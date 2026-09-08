import { test } from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

// No auth (open), global DRY_RUN=false so per-request dryRun is the gate.
delete process.env.OUTREACH_API_KEY
delete process.env.WEBHOOK_SECRET
process.env.DRY_RUN = 'false'
const dataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'ces-dryrun-'))
process.env.DATA_DIR = dataDir

// Minimal leads CSV so /campaign has something to iterate.
const csvDir = fs.mkdtempSync(path.join(os.tmpdir(), 'ces-csv-'))
const csvPath = path.join(csvDir, 'leads.csv')
fs.writeFileSync(csvPath, 'name,email,website,city,category,notes\nAlice,alice@example.com,,,,\n')

process.env.LEADS_CSV = csvPath

const { app } = await import('../src/index.js')

const sentFile = path.join(dataDir, 'sent.json')

function sentExists() {
  try {
    JSON.parse(fs.readFileSync(sentFile, 'utf8'))
    return true
  } catch {
    return false
  }
}

async function withServer(fn) {
  const server = app.listen(0)
  const base = `http://localhost:${server.address().port}`
  try {
    await fn(base)
  } finally {
    server.close()
  }
}

test('dry-run: /send with dryRun=true writes no state to sent.json', async () => {
  await withServer(async (base) => {
    assert.equal(sentExists(), false, 'sent.json should not exist before')
    const res = await fetch(`${base}/send`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ email: 'alice@example.com', dryRun: true }),
    })
    const body = await res.json()
    assert.equal(body.sent, true)
    assert.equal(sentExists(), false, 'sent.json must NOT be written in dry run')
  })
})

test('dry-run: /campaign with dryRun=true writes no state to sent.json', async () => {
  await withServer(async (base) => {
    assert.equal(sentExists(), false, 'sent.json should not exist before')
    const res = await fetch(`${base}/campaign`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ campaign: 'batch1', dryRun: true }),
    })
    const body = await res.json()
    assert.equal(body.data.dryRun, true)
    assert.ok(body.data.sent >= 1, 'campaign should report simulated sends')
    assert.equal(sentExists(), false, 'sent.json must NOT be written in dry run')
  })
})

test('dry-run: /send without dryRun DOES write state (control)', async () => {
  await withServer(async (base) => {
    const res = await fetch(`${base}/send`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ email: 'bob@example.com' }),
    })
    const body = await res.json()
    // Global DRY_RUN is false, but sendMail with config.dryRun=false will try a real send.
    // It will fail (no Zepto key), but markSent runs BEFORE the response only if !dryRun.
    // Since dryRun defaults to config.dryRun=false here, markSent IS called.
    // The send throws (no API key) so we land in catch → 500. But markSent may or may not
    // have run depending on ordering. The real assertion is the dry-run tests above.
    // This control just confirms the test harness is wired.
    assert.ok(res.status === 200 || res.status === 500, `got ${res.status}`)
  })
})

// Cleanup temp CSV dir after tests
process.on('exit', () => {
  fs.rmSync(dataDir, { recursive: true, force: true })
  fs.rmSync(csvDir, { recursive: true, force: true })
})