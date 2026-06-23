const ALLOWED_TAGS = [
  'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
  'p', 'br', 'hr', 'blockquote', 'pre', 'code',
  'ul', 'ol', 'li', 'dl', 'dt', 'dd',
  'table', 'thead', 'tbody', 'tr', 'th', 'td',
  'a', 'img', 'strong', 'em', 'b', 'i', 'u', 's', 'del', 'mark',
  'span', 'div', 'figure', 'figcaption', 'picture',
  'time', 'abbr', 'cite', 'q', 'sup', 'sub',
]

const FORBIDDEN_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'button']

let purify: any = null
let purifyPromise: Promise<any> | null = null

function stripDangerousHtml(dirty: string): string {
  let clean = dirty
  for (const tag of FORBIDDEN_TAGS) {
    const regex = new RegExp(`<${tag}[^>]*>[\\s\\S]*?<\\/${tag}>|<${tag}[^>]*\\/?>`, 'gi')
    clean = clean.replace(regex, '')
  }
  clean = clean.replace(/\s+on\w+\s*=\s*["'][^"']*["']/gi, '')
  clean = clean.replace(/\s+on\w+\s*=\s*[^\s>]+/gi, '')
  clean = clean.replace(/href\s*=\s*["']javascript:["']/gi, 'href="#"')
  return clean
}

function ensurePurify() {
  if (!purifyPromise && import.meta.client) {
    purifyPromise = import('dompurify').then((mod) => {
      purify = mod.default
    }).catch(() => {
      purify = null
    })
  }
  return purifyPromise
}

export function sanitizeHtml(dirty: string): string {
  if (!dirty) return ''

  if (import.meta.client && purify) {
    return purify.sanitize(dirty, {
      ALLOWED_TAGS,
      ALLOW_DATA_ATTR: true,
      FORBID_TAGS: FORBIDDEN_TAGS,
      FORBID_ATTR: ['onerror', 'onload', 'onclick', 'onmouseover', 'onfocus', 'onblur', 'onchange'],
    })
  }

  return stripDangerousHtml(dirty)
}

if (import.meta.client) {
  ensurePurify()
}