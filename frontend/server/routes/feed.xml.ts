export default defineEventHandler(async (event) => {
  const runtimeConfig = useRuntimeConfig(event)
  const backendBase = String(runtimeConfig.backendApiBase || '').replace(/\/$/, '')
  const target = `${backendBase}/feed.xml`

  const res = await fetch(target)
  const xml = await res.text()

  setHeader(event, 'Content-Type', 'application/rss+xml')
  return xml
})
