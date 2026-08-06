import { config } from './config.js'

/**
 * Template renderer.
 * Templates live in ./templates/*.json with fields:
 *   subject, textbody, htmlbody (optional)
 * Placeholders: {{name}}, {{outlet}}, {{website}}, {{city}}, {{category}}, {{notes}}
 * {{unsubscribe}} expands to the unsubscribe footer.
 */

function render(text, lead, campaign) {
  return text
    .replaceAll('{{name}}', lead.name)
    .replaceAll('{{outlet}}', lead.website ? lead.website.replace(/^https?:\/\//, '').replace(/\/$/, '') : lead.name)
    .replaceAll('{{website}}', lead.website)
    .replaceAll('{{city}}', lead.city)
    .replaceAll('{{category}}', lead.category)
    .replaceAll('{{notes}}', lead.notes)
    .replaceAll('{{campaign}}', campaign)
    .replaceAll('{{signature}}', config.senderName)
    .replaceAll('{{unsubscribe}}', config.unsubscribeUrl ? `\n\nUnsubscribe: ${config.unsubscribeUrl}` : '')
}

export function renderTemplate(template, lead, campaign) {
  return {
    subject: render(template.subject, lead, campaign),
    textbody: render(template.textbody, lead, campaign),
    htmlbody: template.htmlbody ? render(template.htmlbody, lead, campaign) : undefined,
  }
}
