export default defineEventHandler(async (event) => {
  const runtimeConfig = useRuntimeConfig(event)
  const backendBase = String(runtimeConfig.backendApiBase || 'http://127.0.0.1:8000').replace(/\/$/, '')
  const path = getRouterParam(event, 'path') ?? ''
  const target = `${backendBase}/storage/${path}`

  return proxyRequest(event, target)
})
