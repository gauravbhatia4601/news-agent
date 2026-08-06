import fs from 'node:fs'
import path from 'node:path'
import { config } from './config.js'
import { loadLeads } from './leads.js'
import { quota, sentStats, sentMeta, followupDue, currentDailyLimit, todayKey, daysSinceStart } from './limiter.js'

const eventsFile = path.join(config.dataDir, 'events.json')

function loadEvents() {
  try {
    return JSON.parse(fs.readFileSync(eventsFile, 'utf8'))
  } catch {
    return []
  }
}

function esc(s) {
  return String(s ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
}

function leadStatus(email) {
  const meta = sentMeta(email)
  if (!meta) return { label: 'pending', cls: 'pending' }
  if (meta.replied) return { label: 'replied', cls: 'replied' }
  if (meta.bounced) return { label: 'bounced', cls: 'bounced' }
  if (meta.skipped) return { label: 'skipped', cls: 'skipped' }
  if (meta.followupAt) return { label: 'follow-up sent', cls: 'followup' }
  return { label: 'sent', cls: 'sent' }
}

const fmt = (iso) => (iso ? new Date(iso).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '—')

const EVENT_CLASS = {
  delivered: 'ok',
  opened: 'open',
  clicked: 'click',
  bounced: 'bounced',
  spam_complaint: 'bounced',
}

export function dashboardHtml() {
  const q = quota()
  const stats = sentStats()
  const leads = loadLeads()
  const events = loadEvents()
  const schedule = config.warmupSchedule
  const day = daysSinceStart()
  const limit = currentDailyLimit()

  const leadRows = leads
    .map((l) => {
      const st = leadStatus(l.email)
      const meta = sentMeta(l.email)
      return `<tr>
        <td>${esc(l.name)}</td>
        <td><a href="mailto:${esc(l.email)}">${esc(l.email)}</a></td>
        <td>${l.website ? `<a href="${esc(l.website)}" target="_blank" rel="noopener">${esc(l.website.replace(/^https?:\/\//, ''))}</a>` : '—'}</td>
        <td>${esc(meta?.template || '—')}</td>
        <td><span class="badge ${st.cls}">${st.label}</span></td>
        <td>${fmt(meta?.at)}</td>
      </tr>`
    })
    .join('')

  const dueRows = leads
    .filter((l) => followupDue(l.email))
    .map(
      (l) => `<tr><td>${esc(l.name)}</td><td><a href="mailto:${esc(l.email)}">${esc(l.email)}</a></td><td>${fmt(sentMeta(l.email)?.at)}</td></tr>`
    )
    .join('')

  const eventRows = events
    .slice(-50)
    .reverse()
    .map(
      (e) => `<tr>
        <td>${fmt(e.at)}</td>
        <td><span class="badge ${EVENT_CLASS[e.event] || ''}">${esc(e.event)}</span></td>
        <td>${esc(e.email)}</td>
      </tr>`
    )
    .join('')

  const warmup = schedule.length
    ? schedule.map((s, i) => `<span class="wday ${i < day - 1 ? 'done' : i === day - 1 ? 'today' : ''}">d${i + 1} · ${s}</span>`).join('')
    : `<span class="wday today">flat · ${limit}/day</span>`

  return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cold Email Dashboard</title>
<style>
  :root { --bg:#0f1115; --panel:#171a21; --border:#262b36; --text:#e6e9ef; --muted:#8b93a3; --accent:#4f8cff; }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--text); font:14px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; }
  .wrap { max-width:1100px; margin:0 auto; padding:24px 16px 64px; }
  h1 { font-size:18px; margin:0 0 4px; }
  h2 { font-size:14px; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin:32px 0 12px; }
  .sub { color:var(--muted); font-size:12px; }
  .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin-top:20px; }
  .card { background:var(--panel); border:1px solid var(--border); border-radius:10px; padding:14px 16px; }
  .card .num { font-size:26px; font-weight:700; margin-top:4px; }
  .card .lbl { color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.06em; }
  .num.green { color:#34d399; } .num.blue { color:var(--accent); } .num.amber { color:#fbbf24; }
  .num.red { color:#f87171; } .num.purple { color:#c084fc; }
  .warmup { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
  .wday { background:var(--panel); border:1px solid var(--border); border-radius:6px; padding:4px 10px; font-size:12px; color:var(--muted); }
  .wday.done { color:var(--muted); opacity:.5; text-decoration:line-through; }
  .wday.today { border-color:var(--accent); color:var(--accent); }
  table { width:100%; border-collapse:collapse; background:var(--panel); border:1px solid var(--border); border-radius:10px; overflow:hidden; }
  th, td { text-align:left; padding:8px 12px; border-bottom:1px solid var(--border); font-size:12.5px; }
  th { color:var(--muted); text-transform:uppercase; font-size:11px; letter-spacing:.06em; background:#1a1e27; }
  tr:last-child td { border-bottom:none; }
  td a { color:var(--accent); text-decoration:none; }
  td a:hover { text-decoration:underline; }
  .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:600; }
  .badge.pending { background:#232838; color:#9aa4b8; }
  .badge.sent { background:#10263b; color:#7dd3fc; }
  .badge.replied { background:#0c2e24; color:#34d399; }
  .badge.bounced { background:#3b1116; color:#f87171; }
  .badge.skipped { background:#3a2a0c; color:#fbbf24; }
  .badge.followup { background:#2a1340; color:#c084fc; }
  .badge.ok { background:#0c2e24; color:#34d399; }
  .badge.open { background:#10263b; color:#7dd3fc; }
  .badge.click { background:#2a1340; color:#c084fc; }
  .empty { color:var(--muted); padding:16px; }
  .footer { margin-top:40px; color:var(--muted); font-size:11px; }
  .pill { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; margin-left:8px; vertical-align:middle; }
  .pill.warn { background:#3a2a0c; color:#fbbf24; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Cold Email Dashboard
    <span class="pill ${config.dryRun ? 'warn' : ''}">${config.dryRun ? 'DRY RUN' : 'LIVE'}</span>
  </h1>
  <div class="sub">sender: ${esc(config.senderEmail)} · ${esc(config.senderName)} · ${todayKey()} (warm-up day ${day})</div>

  <div class="cards">
    <div class="card"><div class="lbl">Sent today</div><div class="num blue">${q.sentToday}</div><div class="sub">of ${q.limit} daily limit</div></div>
    <div class="card"><div class="lbl">Remaining today</div><div class="num green">${q.remaining}</div><div class="sub">resets at midnight</div></div>
    <div class="card"><div class="lbl">Total sent</div><div class="num">${stats.sent}</div><div class="sub">across all waves</div></div>
    <div class="card"><div class="lbl">Replies</div><div class="num green">${stats.replied}</div><div class="sub">${stats.sent ? Math.round((stats.replied / stats.sent) * 100) : 0}% reply rate</div></div>
    <div class="card"><div class="lbl">Follow-ups</div><div class="num purple">${stats.followups}</div><div class="sub">${config.followupDays}-day window</div></div>
    <div class="card"><div class="lbl">Bounced</div><div class="num red">${stats.bounced}</div><div class="sub">${stats.skipped} skipped</div></div>
  </div>

  <h2>Warm-up schedule</h2>
  <div class="warmup">${warmup}</div>

  <h2>Leads (${leads.length})</h2>
  <table>
    <thead><tr><th>Name</th><th>Email</th><th>Outlet</th><th>Template</th><th>Status</th><th>First sent</th></tr></thead>
    <tbody>${leadRows || '<tr><td colspan="6" class="empty">No leads in CSV</td></tr>'}</tbody>
  </table>

  <h2>Follow-ups due now (${dueRows ? leads.filter((l) => followupDue(l.email)).length : 0})</h2>
  <table>
    <thead><tr><th>Name</th><th>Email</th><th>First sent</th></tr></thead>
    <tbody>${dueRows || '<tr><td colspan="3" class="empty">None — run /campaign/followup when these fill up</td></tr>'}</tbody>
  </table>

  <h2>Recent events (${events.length} total)</h2>
  <table>
    <thead><tr><th>Time</th><th>Event</th><th>Email</th></tr></thead>
    <tbody>${eventRows || '<tr><td colspan="3" class="empty">No webhook events yet — configure the ZeptoMail webhook to this server</td></tr>'}</tbody>
  </table>

  <div class="footer">API: <a href="/quota">/quota</a> · <a href="/stats">/stats</a> · <a href="/leads">/leads</a> · <a href="/followup/due">/followup/due</a> · refresh every 30s</div>
</div>
<script>
  setTimeout(() => location.reload(), 30000)
</script>
</body>
</html>`
}
