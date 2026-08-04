export default defineEventHandler(async (event) => {
  const runtimeConfig = useRuntimeConfig(event)
  const backendBase = String(runtimeConfig.backendApiBase || '').replace(/\/$/, '')
  const path = getRouterParam(event, 'path') ?? ''
  const { search } = getRequestURL(event)
  const target = `${backendBase}/api/${path}${search}`

  return proxyRequest(event, target)
})
