export default defineEventHandler(async (event) => {
  const runtimeConfig = useRuntimeConfig(event)
  const client = String(runtimeConfig.public.adsenseClient || '')

  if (!client) {
    return ''
  }

  return `google.com, ${client}, DIRECT, f08c47fec0942fa0\n`
})