import { test } from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

// Env set BEFORE the dynamic import so config.js picks it up.
// One env config per file — node --test runs each file in its own process.
process.env.OUTREACH_API_KEY = 'secret123'
process.env.WEBHOOK_SECRET = 'wh-secret'
process.env.DRY_RUN = 'true'
const dataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'ces-auth-'))
process.env.DATA_DIR = dataDir

const { app } = await import('../src/index.js')

function listen(app) {
  return new Promise((resolve) => app.listen(0, () => resolve(app.address ? null : null)))
}

async function withServer(fn) {
  const server = app.listen(0)
  const port = server.address().port
  try {
    await fn(`http://localhost:${port}`)
  } finally {
    server.close()
    fs.rmSync(dataDir, { recursive: true, force: true })
  }
}

async function req(base, p, opts = {}) {
  const res = await fetch(`${base}${p}`, opts)
  const body = await res.json().catch(() => ({}))
  return { status: res.status, body }
}

test('auth: 401 on /send when OUTREACH_API_KEY set and no bearer token', async () => {
  await withServer(async (base) => {
    const r = await req(base, '/send', { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ email: 'a@b.com' }) })
    assert.equal(r.status, 401)
    assert.equal(r.body.error, 'Unauthorized')
  })
})

test('auth: 401 with wrong bearer token', async () => {
  await withServer(async (base) => {
    const r = await req(base, '/send', { method: 'POST', headers: { 'content-type': 'application/json', authorization: 'Bearer wrong' }, body: JSON.stringify({ email: 'a@b.com' }) })
    assert.equal(r.status, 401)
  })
})

test('auth: passes with correct Bearer key', async () => {
  await withServer(async (base) => {
    const r = await req(base, '/send', {
      method: 'POST',
      headers: { 'content-type': 'application/json', authorization: 'Bearer secret123' },
      body: JSON.stringify({ email: 'a@b.com', dryRun: true }),
    })
    assert.notEqual(r.status, 401)
    assert.equal(r.body.sent, true)
  })
})

test('auth: 403 on /replied without bearer', async () => {
  await withServer(async (base) => {
    const r = await req(base, '/replied', { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ email: 'a@b.com' }) })
    assert.equal(r.status, 401)
  })
})

test('webhook: 403 when WEBHOOK_SECRET set and token missing', async () => {
  await withServer(async (base) => {
    const r = await req(base, '/webhook/zeptomail', { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ event: 'delivered' }) })
    assert.equal(r.status, 403)
  })
})

test('webhook: 403 with wrong token', async () => {
  await withServer(async (base) => {
    const r = await req(base, '/webhook/zeptomail?token=nope', { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ event: 'delivered' }) })
    assert.equal(r.status, 403)
  })
})

test('webhook: passes with ?token= query', async () => {
  await withServer(async (base) => {
    const r = await req(base, '/webhook/zeptomail?token=wh-secret', { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ event: 'delivered' }) })
    assert.equal(r.status, 200)
    assert.equal(r.body.ok, true)
  })
})

test('webhook: passes with X-Webhook-Secret header', async () => {
  await withServer(async (base) => {
    const r = await req(base, '/webhook/zeptomail', { method: 'POST', headers: { 'content-type': 'application/json', 'x-webhook-secret': 'wh-secret' }, body: JSON.stringify({ event: 'delivered' }) })
    assert.equal(r.status, 200)
    assert.equal(r.body.ok, true)
  })
})