# Cold Email Service

Pluggable microservice for personalized cold outreach via ZeptoMail, with daily throttling, warm-up schedule, and dedupe. Designed so any project (Laravel, Nuxt, standalone) can call it over HTTP.

## Features

- **HTTP API** — `POST /send` (one lead), `POST /campaign` (CSV batch), `GET /quota`, `GET /leads`, `POST /replied`
- **ZeptoMail integration** — transactional email API by Zoho
- **Daily throttle** — hard cap per day, configurable
- **Warm-up schedule** — ramps daily caps over N days (e.g. `10,15,20,25,30`) to protect domain reputation
- **Dedupe** — tracks sent emails in `data/sent.json`; survives restarts
- **Personalized templates** — `{{name}} {{outlet}} {{website}} {{city}} {{category}} {{notes}}` placeholders
- **Reply tracking** — `Reply-To: sender+outreach@domain` tag
- **Unsubscribe footer** — appended to every send
- **Dry-run mode** — test without sending

## Setup

```bash
cd cold-email-service
cp .env.example .env   # fill in ZEPTO_API_KEY + SENDER_EMAIL (verified domain)
npm install
cp ../leads/leads-batch1.csv leads.csv   # or drop any leads CSV
```

## Run

```bash
npm start              # port 4100 by default
```

## API

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/health` | liveness |
| GET | `/quota` | sent today / limit / remaining |
| GET | `/leads` | list leads from CSV |
| POST | `/send` | send one email `{email, name?, website?, city?, category?, notes?, template?, campaign?}` |
| POST | `/campaign` | batch-send CSV `{template?, campaign?, limit?}` |
| POST | `/replied` | mark lead as replied `{email}` |

## Lead CSV format

```csv
name,email,website,city,category,notes,source_url
```

## A/B testing templates

Five templates ship by default — run each against a different lead segment and compare reply rates in the ZeptoMail dashboard:

| Template | Angle | File |
|----------|-------|------|
| `cold` | Baseline: quality + pain + solution | `templates/cold.json` |
| `cold-pain` | Cost-focused (₹/article, ₹/writer math) | `templates/cold-pain.json` |
| `cold-proof` | "It's live, not a pitch deck" | `templates/cold-proof.json` |
| `cold-direct` | Shortest: quality + solution + CTA | `templates/cold-direct.json` |
| `cold-radar` | Editorial angle: coverage-gap radar | `templates/cold-radar.json` |

Run a specific template:

```bash
curl -X POST http://localhost:4100/campaign \
  -H 'Content-Type: application/json' \
  -d '{"campaign":"batch1-pain","template":"cold-pain","limit":10}'
```

Each campaign tag (`campaign` field) is passed to ZeptoMail as a tag — filter by tag in the ZeptoMail dashboard to compare results.

## Integration

From any project:

```bash
curl -X POST http://localhost:4100/send \
  -H 'Content-Type: application/json' \
  -d '{"email":"hello@example.com","name":"Editor","campaign":"batch1"}'

curl -X POST http://localhost:4100/campaign \
  -H 'Content-Type: application/json' \
  -d '{"campaign":"batch1","limit":25}'
```

## Compliance notes

- Start slow: 10–25/day, ramp weekly. Cold blasts from a new domain destroy deliverability.
- Keep the unsubscribe link — required by CAN-SPAM and good practice in India.
- Personalize per lead; generic blasts get flagged by ZeptoMail's spam filters.
- Monitor `GET /quota` daily; keep `data/sent.json` backed up.
