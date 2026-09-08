import 'dotenv/config'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const root = path.resolve(__dirname, '..')

export const config = {
  zeptoApiKey: process.env.ZEPTO_API_KEY || '',
  zeptoApiBase: process.env.ZEPTO_API_BASE || 'https://api.zeptomail.com',
  senderEmail: process.env.SENDER_EMAIL || '',
  senderName: process.env.SENDER_NAME || '',
  signature: process.env.SIGNATURE || process.env.SENDER_NAME || '',
  dailyLimit: parseInt(process.env.DAILY_LIMIT || '25', 10),
  interSendDelay: parseInt(process.env.INTER_SEND_DELAY || '0', 10),
  followupDays: parseInt(process.env.FOLLOWUP_DAYS || '6', 10),
  warmupSchedule: (process.env.WARMUP_SCHEDULE || '')
    .split(',')
    .map((s) => parseInt(s.trim(), 10))
    .filter((n) => !Number.isNaN(n)),
  leadsCsv: path.resolve(root, process.env.LEADS_CSV || './leads.csv'),
  replyTagEnabled: process.env.REPLY_TAG_ENABLED !== 'false',
  unsubscribeUrl: process.env.UNSUBSCRIBE_URL || '',
  port: parseInt(process.env.PORT || '4100', 10),
  // Sending disabled by default (dry-run); opt in explicitly with DRY_RUN=false
  dryRun: process.env.DRY_RUN !== 'false',
  // Override data dir for tests (defaults to ./data); absolute paths win
  dataDir: path.resolve(root, process.env.DATA_DIR || 'data'),
  // Shared-secret bearer auth for mutating endpoints; unset = open (dev) + startup warn
  outreachApiKey: process.env.OUTREACH_API_KEY || '',
  // Webhook secret for /webhook/zeptomail; unset = open (dev) + startup warn
  webhookSecret: process.env.WEBHOOK_SECRET || '',
  // CORS allowlist for mutating endpoints (comma-separated origins); unset = no CORS headers
  allowedOrigins: (process.env.ALLOWED_ORIGINS || '').split(',').map((s) => s.trim()).filter(Boolean),
}
