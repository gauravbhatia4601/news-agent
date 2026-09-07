import fs from 'node:fs'
import path from 'node:path'
import { config } from './config.js'

/**
 * Daily quota + dedupe tracker.
 * Persists to data/sent.json so state survives restarts.
 *
 * State shape:
 * {
 *   byDate: { "2026-08-06": 5 },
 *   sent: {
 *     "email@x.com": {
 *       at: ISO timestamp,        // when first sent
 *       campaign: "batch1",
 *       template: "cold",
 *       replied: false,
 *       followupAt: ISO|null,     // when follow-up was sent
 *       followupTemplate: null,
 *       bounced: false,
 *       skipped: false            // marked skipped (no email / hard failure)
 *     }
 *   }
 * }
 */
const sentFile = path.join(config.dataDir, 'sent.json')

function emptyState() {
  return { byDate: {}, sent: {} }
}

function load() {
  let state
  try {
    state = JSON.parse(fs.readFileSync(sentFile, 'utf8'))
  } catch {
    return emptyState()
  }
  // migrate old format { byDate: {}, sent: ["a@b.com", ...] } → map
  if (Array.isArray(state.sent)) {
    const map = {}
    for (const email of state.sent) map[email] = { at: new Date().toISOString(), campaign: 'unknown' }
    state.sent = map
  }
  return state
}

function save(state) {
  fs.mkdirSync(config.dataDir, { recursive: true })
  fs.writeFileSync(sentFile, JSON.stringify(state, null, 2))
}

export function todayKey() {
  return new Date().toISOString().slice(0, 10)
}

export function daysSinceStart() {
  // Explicit ramp anchor wins when set. An unparseable value falls through to
  // the data-anchored path — never fail open to the max schedule limit.
  if (process.env.START_DATE) {
    const start = new Date(process.env.START_DATE)
    if (!Number.isNaN(start.getTime())) {
      return Math.max(1, Math.floor((Date.now() - start.getTime()) / 86400000) + 1)
    }
  }
  // Otherwise anchor to the first recorded send day (earliest byDate key),
  // so the ramp progresses naturally from first real use. No dated state → day 1.
  const dates = Object.keys(load().byDate).sort()
  if (dates.length === 0) return 1
  const start = new Date(dates[0] + 'T00:00:00Z')
  return Math.max(1, Math.floor((Date.now() - start.getTime()) / 86400000) + 1)
}

export function currentDailyLimit() {
  const schedule = config.warmupSchedule
  if (!schedule.length) return config.dailyLimit
  const idx = daysSinceStart() - 1
  return idx < schedule.length ? schedule[idx] : schedule[schedule.length - 1]
}

export function quota() {
  const state = load()
  const sentToday = state.byDate[todayKey()] || 0
  const limit = currentDailyLimit()
  return { sentToday, limit, remaining: Math.max(0, limit - sentToday) }
}

export function alreadySent(email) {
  const state = load()
  return Boolean(state.sent[email.toLowerCase()])
}

export function sentMeta(email) {
  const state = load()
  return state.sent[email.toLowerCase()] || null
}

export function markSent(email, meta = {}) {
  const state = load()
  const key = email.toLowerCase()
  state.byDate[todayKey()] = (state.byDate[todayKey()] || 0) + 1
  state.sent[key] = { at: new Date().toISOString(), replied: false, followupAt: null, followupTemplate: null, bounced: false, skipped: false, ...meta, ...state.sent[key] }
  save(state)
}

export function markSkipped(email, reason = '') {
  const state = load()
  const key = email.toLowerCase()
  state.sent[key] = { ...state.sent[key], skipped: true, skipReason: reason }
  save(state)
}

export function markBounced(email) {
  const state = load()
  const key = email.toLowerCase()
  state.sent[key] = { ...state.sent[key], bounced: true }
  save(state)
}

export function markReplied(email) {
  const state = load()
  const key = email.toLowerCase()
  state.sent[key] = { ...state.sent[key], replied: true }
  save(state)
}

export function markFollowupSent(email, template) {
  const state = load()
  const key = email.toLowerCase()
  state.byDate[todayKey()] = (state.byDate[todayKey()] || 0) + 1
  state.sent[key] = { ...state.sent[key], followupAt: new Date().toISOString(), followupTemplate: template }
  save(state)
}

/**
 * Eligible for follow-up: sent, not replied, not bounced/skipped,
 * no follow-up yet, and at least FOLLOWUP_DAYS old.
 */
export function followupDue(email) {
  const meta = sentMeta(email)
  if (!meta || !meta.at || meta.replied || meta.bounced || meta.skipped || meta.followupAt) return false
  const ageDays = (Date.now() - new Date(meta.at).getTime()) / 86400000
  return ageDays >= config.followupDays
}

export function sentStats() {
  const state = load()
  const entries = Object.values(state.sent)
  return {
    sent: entries.filter((e) => e.at).length,
    replied: entries.filter((e) => e.replied).length,
    followups: entries.filter((e) => e.followupAt).length,
    skipped: entries.filter((e) => e.skipped).length,
    bounced: entries.filter((e) => e.bounced).length,
    sentToday: state.byDate[todayKey()] || 0,
    byDate: state.byDate,
  }
}
