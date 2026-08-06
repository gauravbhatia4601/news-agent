# Cold Email Service

Pluggable microservice for personalized cold outreach via ZeptoMail, with daily throttling, warm-up schedule, A/B templates, follow-ups, and dedupe. Designed so any project (Laravel, Nuxt, standalone) can call it over HTTP.

## Features

- **HTTP API** — `POST /send` (one lead), `POST /campaign` (CSV batch, A/B split), `POST /campaign/followup`, `GET /quota`, `GET /leads`, `GET /stats`, `POST /replied`
- **ZeptoMail integration** — transactional email API by Zoho (regional endpoints supported, e.g. `.ae`)
- **Daily throttle** — hard cap per day, configurable, with warm-up schedule ramp (`10,15,20,25,30`)
- **Inter-send pacing** — configurable pause between sends so emails don't burst out
- **A/B template split** — assign leads round-robin across weighted template lists in one run
- **Follow-ups** — automatic detection of leads sent ≥ N days ago with no reply; skip replied/bounced
- **Dedupe** — tracks send state (sent/replied/bounced/skipped) in `data/sent.json`; survives restarts
- **Personalized templates** — `{{name}} {{outlet}} {{website}} {{city}} {{category}} {{notes}}` placeholders
- **Reply tracking** — `Reply-To: sender+outreach@domain` tag
- **Unsubscribe footer** — appended to every send
- **Webhook receiver** — ZeptoMail pushes delivered/opened/clicked/bounced/complaint events here
- **Dry-run mode** — test the full flow without sending

## Setup

```bash
cd cold-email-service
cp .env.example .env   # fill in ZEPTO_API_KEY + SENDER_EMAIL (verified domain)
npm install
```

`LEADS_CSV` defaults to `../leads/leads-batch1.csv` (project leads folder). Drop any CSV with `name,email,website,city,category,notes,source_url` columns.

## Run

```bash
npm start              # port 4100 by default
```

Or as a systemd service (survives reboots):

```bash
systemctl start cold-email
systemctl status cold-email
```

## API

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/health` | liveness |
| GET | `/quota` | sent today / limit / remaining |
| GET | `/leads` | list leads from CSV |
| GET | `/stats` | sent/replied/followups/bounced + delivery events |
| GET | `/followup/due` | preview who is eligible for a follow-up |
| POST | `/send` | send one email `{email, name?, website?, template?, campaign?, dryRun?}` |
| POST | `/campaign` | batch-send CSV with A/B split `{template? | templates?, weights?, campaign?, limit?, dryRun?}` |
| POST | `/campaign/followup` | send follow-ups to non-repliers aged ≥ `FOLLOWUP_DAYS` `{template?, campaign?, limit?, dryRun?}` |
| POST | `/webhook/zeptomail` | ZeptoMail delivery events receiver |
| POST | `/replied` | mark lead as replied `{email}` |

### A/B split example

```bash
curl -X POST http://localhost:4100/campaign \
  -H 'Content-Type: application/json' \
  -d '{"templates":["cold","cold-pain","cold-proof","cold-direct","cold-radar"],"weights":[1,1,1,1,1],"campaign":"batch1-ab","limit":15}'
```

Leads are assigned round-robin across the weighted template list. Each email is tagged `campaign-template` in ZeptoMail so you can compare reply rates per variant in the dashboard or via `/stats`.

### Follow-up flow

1. `POST /campaign` sends Wave 1.
2. After `FOLLOWUP_DAYS` (default 6), run `POST /campaign/followup` — it sends the `followup` template only to leads that were sent, didn't reply, didn't bounce, and have no follow-up yet.
3. `POST /replied` removes a lead from the follow-up queue.

## Lead CSV format

```csv
name,email,website,city,category,notes,source_url
```

Rows without a valid email are skipped.

## Templates

Six templates ship by default:

| Template | Angle | File |
|----------|-------|------|
| `cold` | Baseline: quality + pain + solution | `templates/cold.json` |
| `cold-pain` | Cost-focused (₹/article, ₹/writer math) | `templates/cold-pain.json` |
| `cold-proof` | "It's live, not a pitch deck" | `templates/cold-proof.json` |
| `cold-direct` | Shortest: quality + solution + CTA | `templates/cold-direct.json` |
| `cold-radar` | Editorial angle: coverage-gap radar | `templates/cold-radar.json` |
| `followup` | Nudge for non-repliers (used by `/campaign/followup`) | `templates/followup.json` |

Add your own: drop a JSON file in `templates/` with `subject`, `textbody` (placeholders supported), and optional `htmlbody`.

## ZeptoMail webhooks

In the ZeptoMail dashboard → Agent → Webhooks, point the webhook URL at `https://<public-host>/webhook/zeptomail`. Events (delivered, opened, clicked, bounced, spam_complaint) are stored in `data/events.json` and rolled up by `GET /stats`. Bounces automatically stop re-sending to that address.

## Compliance notes

- Start slow: 10–25/day, ramp weekly. Cold blasts from a new domain destroy deliverability.
- Keep the unsubscribe link — required by CAN-SPAM and good practice in India.
- Personalize per lead; generic blasts get flagged by ZeptoMail's spam filters.
- Monitor `GET /quota` daily; keep `data/sent.json` backed up.
