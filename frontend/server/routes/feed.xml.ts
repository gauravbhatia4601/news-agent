export default defineEventHandler(async (event) => {
  const runtimeConfig = useRuntimeConfig(event)
  const backendBase = String(runtimeConfig.backendApiBase || '').replace(/\/$/, '')
  const target = `${backendBase}/feed.xml`

  try {
    const res = await fetch(target)
    if (!res.ok) {
      setResponseStatus(event, res.status)
      setHeader(event, 'Content-Type', 'application/rss+xml')
      return '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Feed unavailable</title></channel></rss>'
    }
    const xml = await res.text()
    setHeader(event, 'Content-Type', 'application/rss+xml')
    return xml
  } catch {
    setResponseStatus(event, 502)
    setHeader(event, 'Content-Type', 'application/rss+xml')
    return '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Feed unavailable</title></channel></rss>'
  }
})