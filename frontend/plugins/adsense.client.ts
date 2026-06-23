export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  const client = config.public.adsenseClient as string

  if (client) {
    useHead({
      script: [
        {
          src: `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${client}`,
          async: true,
          crossorigin: 'anonymous',
        },
      ],
    })
  }
})