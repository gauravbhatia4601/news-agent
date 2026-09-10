export default defineEventHandler((event) => {
  const runtimeConfig = useRuntimeConfig(event)
  const backendBase = String(runtimeConfig.backendApiBase || '').replace(/\/$/, '')
  const target = `${backendBase}/sitemap.xml`

  return proxyRequest(event, target)
})