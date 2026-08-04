export default defineEventHandler(async (event) => {
  const runtimeConfig = useRuntimeConfig(event)
  const backendBase = String(runtimeConfig.backendApiBase || '').replace(/\/$/, '')

  const query = getQuery(event)
  const file = query.file as string | undefined

  const target = file
    ? `${backendBase}/sitemaps/${file}`
    : `${backendBase}/sitemaps/sitemap.xml`

  try {
    const res = await fetch(target)
    if (!res.ok) {
      setResponseStatus(event, res.status)
      return `Sitemap not found (${res.status})`
    }
    const xml = await res.text()
    setHeader(event, 'Content-Type', 'application/xml')
    return xml
  } catch {
    setResponseStatus(event, 502)
    return 'Sitemap unavailable'
  }
})
