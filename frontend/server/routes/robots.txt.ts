export default defineEventHandler(async (event) => {
  const runtimeConfig = useRuntimeConfig(event)
  const backendBase = String(runtimeConfig.backendApiBase || '').replace(/\/$/, '')
  const target = `${backendBase}/robots.txt`

  return proxyRequest(event, target)
})
