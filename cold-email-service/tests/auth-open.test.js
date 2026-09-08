import { test } from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

// No auth secrets set — endpoints should be open (dev mode).
delete process.env.OUTREACH_API_KEY
delete process.env.WEBHOOK_SECRET
process.env.DRY_RUN = 'true'
const dataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'ces-open-'))
process.env.DATA_DIR = dataDir

const { app } = await import('../src/index.js')

async function withServer(fn) {
  const server = app.listen(0)
  const base = `http://localhost:${server.address().port}`
  try {
    await fn(base)
  } finally {
    server.close()
    fs.rmSync(dataDir, { recursive: true, force: true })
  }
}

test('auth-open: /send accepts without Authorization header when key unset', async () => {
  await withServer(async (base) => {
    const res = await fetch(`${base}/send`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ email: 'a@b.com', dryRun: true }),
    })
    const body = await res.json()
    assert.notEqual(res.status, 401)
    assert.equal(body.sent, true)
  })
})

test('auth-open: /replied accepts without Authorization header when key unset', async () => {
  await withServer(async (base) => {
    const res = await fetch(`${base}/replied`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ email: 'a@b.com' }),
    })
    assert.notEqual(res.status, 401)
    assert.equal(res.status, 200)
  })
})

test('auth-open: webhook accepts without secret when WEBHOOK_SECRET unset', async () => {
  await withServer(async (base) => {
    const res = await fetch(`${base}/webhook/zeptomail`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ event: 'delivered' }),
    })
    assert.notEqual(res.status, 403)
    assert.equal(res.status, 200)
  })
})