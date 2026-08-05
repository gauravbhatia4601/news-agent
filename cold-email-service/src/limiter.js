import fs from 'node:fs'
import path from 'node:path'
import { config } from './config.js'

/**
 * Daily quota + dedupe tracker.
 * Persists to data/sent.json so state survives restarts.
 */
const sentFile = path.join(config.dataDir, 'sent.json')

function load() {
  try {
    return JSON.parse(fs.readFileSync(sentFile, 'utf8'))
  } catch {
    return { byDate: {}, sent: [] }
  }
}

function save(state) {
  fs.mkdirSync(config.dataDir, { recursive: true })
  fs.writeFileSync(sentFile, JSON.stringify(state, null, 2))
}

export function todayKey() {
  return new Date().toISOString().slice(0, 10)
}

export function daysSinceStart() {
  const start = new Date(process.env.START_DATE || new Date().toISOString())
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
  return state.sent.includes(email.toLowerCase())
}

export function markSent(email) {
  const state = load()
  const key = email.toLowerCase()
  state.byDate[todayKey()] = (state.byDate[todayKey()] || 0) + 1
  if (!state.sent.includes(key)) state.sent.push(key)
  save(state)
}

export function markSkipped(email) {
  const state = load()
  const key = email.toLowerCase()
  if (!state.sent.includes(key)) state.sent.push(key)
  save(state)
}
