import { config } from './config.js'

/**
 * ZeptoMail API client (transactional email API by Zoho).
 * Docs: https://developers.zeptomail.com
 */
export async function sendMail({ to, subject, textbody, htmlbody, replyTo, tags = [] }) {
  if (config.dryRun) {
    console.log(`[dry-run] to=${to} subject=${subject}`)
    return { dryRun: true, to, subject }
  }

  const payload = {
    from: { address: config.senderEmail, name: config.senderName },
    to: [{ email_address: to }],
    subject,
    textbody,
    ...(htmlbody ? { htmlbody } : {}),
    ...(replyTo ? { reply_to: replyTo } : {}),
    ...(tags.length ? { tags } : {}),
  }

  const res = await fetch('https://api.zeptomail.com/v1.1/email/single', {
    method: 'POST',
    headers: {
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
