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
  dailyLimit: parseInt(process.env.DAILY_LIMIT || '25', 10),
  warmupSchedule: (process.env.WARMUP_SCHEDULE || '')
    .split(',')
    .map((s) => parseInt(s.trim(), 10))
    .filter((n) => !Number.isNaN(n)),
  leadsCsv: path.resolve(root, process.env.LEADS_CSV || './leads.csv'),
  replyTagEnabled: process.env.REPLY_TAG_ENABLED !== 'false',
  unsubscribeUrl: process.env.UNSUBSCRIBE_URL || '',
  port: parseInt(process.env.PORT || '4100', 10),
  dryRun: process.env.DRY_RUN === 'true',
  dataDir: path.resolve(root, 'data'),
}
