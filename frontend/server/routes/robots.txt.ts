export default defineEventHandler(async (event) => {
  const runtimeConfig = useRuntimeConfig(event)
  const backendBase = String(runtimeConfig.backendApiBase || 'http://127.0.0.1:8000').replace(/\/$/, '')
  const target = `${backendBase}/robots.txt`

  return proxyRequest(event, target)
})
