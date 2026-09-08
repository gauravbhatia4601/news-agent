import { test } from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

// Open auth, dry-run so no real sends.
delete process.env.OUTREACH_API_KEY
delete process.env.WEBHOOK_SECRET
process.env.DRY_RUN = 'true'
const dataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'ces-val-'))
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

async function sendBody(base, body) {
  const res = await fetch(`${base}/send`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify(body),
  })
  return { status: res.status, body: await res.json().catch(() => ({})) }
}

test('validation: rejects missing email with 400', async () => {
  await withServer(async (base) => {
    const r = await sendBody(base, { dryRun: true })
    assert.equal(r.status, 400)
    assert.equal(r.body.error, 'email is required')
  })
})

test('validation: rejects invalid email shapes with 400', async () => {
  await withServer(async (base) => {
    for (const bad of ['notanemail', 'a@', '@b.com', 'a b@c.com', 'plainaddress', 'a@b']) {
      const r = await sendBody(base, { email: bad, dryRun: true })
      assert.equal(r.status, 400, `expected 400 for "${bad}", got ${r.status}`)
      assert.equal(r.body.error, 'invalid email')
    }
  })
})

test('validation: accepts well-formed email (dry-run sends)', async () => {
  await withServer(async (base) => {
    const r = await sendBody(base, { email: 'good@example.com', dryRun: true })
    assert.equal(r.status, 200)
    assert.equal(r.body.sent, true)
  })
})

test('validation: rejects oversized body (>64kb) with 413', async () => {
  await withServer(async (base) => {
    const big = 'x'.repeat(70 * 1024)
    const res = await fetch(`${base}/send`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ email: 'a@b.com', notes: big }),
    })
    assert.equal(res.status, 413, `expected 413 for oversized body, got ${res.status}`)
  })
})