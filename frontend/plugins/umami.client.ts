export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  const websiteId = config.public.umamiWebsiteId as string
  // Reuse the same cookie name/values as CookieConsent.vue
  const consent = useCookie<'accepted' | 'declined' | null>('cookie-consent')

  // useHead with a reactive getter — script only injected when consent is accepted
  useHead(() => ({
    script: websiteId && consent.value === 'accepted'
      ? [{
          src: 'https://umami.technioz.com/script.js',
          defer: true,
          'data-website-id': websiteId,
        }]
      : [],
  }))
})