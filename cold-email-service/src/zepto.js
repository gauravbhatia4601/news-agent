import { config } from './config.js'

/** True for ZeptoMail 4xx responses (bad address, auth, etc.) — retrying won't help */
export function isPermanentError(err) {
  const m = /^ZeptoMail error (\d+)/.exec(err?.message || '')
  return Boolean(m && Number(m[1]) < 500)
}

/**
 * ZeptoMail API client (transactional email API by Zoho).
 * Docs: https://www.zoho.com/zeptomail/help/api/email-sending.html
 */
export async function sendMail({ to, name = '', subject, textbody, htmlbody, replyTo, tags = [], dryRun = false }) {
  if (config.dryRun || dryRun) {
    console.log(`[dry-run] to=${to} subject=${subject}`)
    return { dryRun: true, to, subject }
  }

  const payload = {
    from: { address: config.senderEmail, name: config.senderName },
    to: [{ email_address: { address: to, ...(name ? { name } : {}) } }],
    subject,
    textbody,
    ...(htmlbody ? { htmlbody } : {}),
    ...(replyTo ? { reply_to: [{ address: replyTo.address, name: replyTo.name }] } : {}),
    ...(tags.length ? { client_reference: tags.join('-') } : {}),
    track_opens: true,
    track_clicks: true,
  }

  const url = `${config.zeptoApiBase}/v1.1/email`
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: `Zoho-enczapikey ${config.zeptoApiKey}`,
    },
    body: JSON.stringify(payload),
  })

  const body = await res.json().catch(() => ({}))
  if (!res.ok) {
    throw new Error(`ZeptoMail error ${res.status}: ${JSON.stringify(body)}`)
  }
  return body
}
