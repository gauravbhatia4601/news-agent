export default defineEventHandler(async (event) => {
  const runtimeConfig = useRuntimeConfig(event)
  const backendBase = String(runtimeConfig.backendApiBase || '').replace(/\/$/, '')
  const path = getRouterParam(event, 'path') ?? ''
  const target = `${backendBase}/storage/${path}`

  return proxyRequest(event, target)
})
