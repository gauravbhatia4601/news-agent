import { test } from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

// Set DATA_DIR before importing so all state lands in a temp dir.
delete process.env.OUTREACH_API_KEY
delete process.env.WEBHOOK_SECRET
process.env.DRY_RUN = 'true'
const dataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'ces-lim-'))
process.env.DATA_DIR = dataDir

const { markSent, sentMeta } = await import('../src/limiter.js')
const { recordEvent } = await import('../src/index.js')

const sentFile = path.join(dataDir, 'sent.json')
const eventsFile = path.join(dataDir, 'events.json')

test('limiter: atomic write — markSent produces valid sent.json and no leftover temp files', () => {
  assert.equal(fs.existsSync(sentFile), false)
  markSent('alice@example.com', { campaign: 'batch1', template: 'cold' })
  // File exists and is valid JSON
  const state = JSON.parse(fs.readFileSync(sentFile, 'utf8'))
  assert.ok(state.byDate)
  assert.ok(state.sent['alice@example.com'])
  assert.equal(state.sent['alice@example.com'].campaign, 'batch1')
  // No leftover temp files (atomic rename completed)
  const leftovers = fs.readdirSync(dataDir).filter((f) => f.includes('.tmp-'))
  assert.deepEqual(leftovers, [], `leftover temp files: ${leftovers}`)
  // sentMeta reads back consistently
  assert.equal(sentMeta('alice@example.com')?.campaign, 'batch1')
})

test('limiter: recordEvent caps events at 5000 and drops oldest', () => {
  fs.mkdirSync(dataDir, { recursive: true })
  // Seed with exactly 5000 events
  const seeded = Array.from({ length: 5000 }, (_, i) => ({ at: '2026-01-01T00:00:00Z', event: 'delivered', seq: i }))
  fs.writeFileSync(eventsFile, JSON.stringify(seeded, null, 2))
  // recordEvent adds one more → 5001 → should cap to 5000, dropping the oldest
  recordEvent({ event: 'opened', email: 'cap@example.com' })

  const events = JSON.parse(fs.readFileSync(eventsFile, 'utf8'))
  assert.equal(events.length, 5000, `expected 5000 events, got ${events.length}`)
  // Oldest (seq:0) must be dropped; the newest is our opened event
  assert.equal(events[0].seq, 1, 'oldest event (seq:0) should have been dropped')
  const last = events[events.length - 1]
  assert.equal(last.event, 'opened')
  assert.equal(last.email, 'cap@example.com')
  // No leftover temp files
  const leftovers = fs.readdirSync(dataDir).filter((f) => f.includes('.tmp-'))
  assert.deepEqual(leftovers, [], `leftover temp files: ${leftovers}`)
})

test('limiter: recordEvent below cap does not trim', () => {
  fs.rmSync(eventsFile, { force: true })
  for (let i = 0; i < 3; i++) recordEvent({ event: 'delivered', email: `x${i}@ex.com` })
  const events = JSON.parse(fs.readFileSync(eventsFile, 'utf8'))
  assert.equal(events.length, 3)
})

process.on('exit', () => fs.rmSync(dataDir, { recursive: true, force: true }))