<template>
  <div class="prose prose-slate max-w-none">
    <h1>Privacy Policy</h1>
    <p class="text-sm text-muted-foreground">Last updated: June 2026</p>

    <h2>1. Information We Collect</h2>
    <p>
      <strong>The Neural Journal collects the following personal data:</strong>
    </p>
    <ul>
      <li><strong>Email addresses</strong> — when you subscribe to our newsletter, we store your email address to deliver news updates.</li>
      <li><strong>IP addresses</strong> — temporarily stored in server session logs for security and abuse prevention. Admin actions are logged with IP address for audit purposes.</li>
      <li><strong>Page-view data</strong> — collected via Umami, a privacy-focused analytics tool. Umami uses cookieless tracking and does not store IP addresses or build user profiles.</li>
    </ul>
    <p>
      We do <strong>not</strong> use advertising cookies, third-party ad networks, social media trackers, or external comment systems.
    </p>

    <h2>2. How We Use Information</h2>
    <p>
      Email addresses are used solely to deliver our newsletter. Anonymized analytics help us understand which topics and articles readers find valuable. IP addresses are used for security monitoring and abuse prevention. This data is never shared, sold, or used for targeted advertising.
    </p>

    <h2>3. Cookies</h2>
    <p>
      We use cookieless analytics via Umami — no tracking cookies are set for public visitors. Admin panel sessions use strictly necessary cookies for authentication, used by authorized staff only.
    </p>

    <h2>4. Newsletter Subscription</h2>
    <p>
      When you subscribe to our newsletter, we store your email address and the date of subscription. You can unsubscribe at any time by contacting us at
      <a href="mailto:hello@theneuraljournal.com">hello@theneuraljournal.com</a>. Your email will be removed from our mailing list upon request.
    </p>

    <h2>5. Third-Party Services</h2>
    <p>
      Analytics are hosted on Umami (umami.technioz.com), operated by the same parent entity. No data is sent to third-party analytics providers like Google Analytics or Meta Pixel.
    </p>

    <h2>6. Data Retention</h2>
    <p>
      Newsletter subscriber emails are retained until you unsubscribe or request deletion. Anonymized page-view counts are retained for trend analysis. Server logs are retained for 14 days. Audit logs containing admin actions and IP addresses are retained for security purposes.
    </p>

    <h2>7. Your Rights (GDPR / CCPA)</h2>
    <p>
      You have the right to access, correct, or delete your personal data. To exercise these rights, contact us at
      <a href="mailto:hello@theneuraljournal.com">hello@theneuraljournal.com</a>. We will respond within 30 days. If you are an EU resident, you also have the right to lodge a complaint with your local data protection authority.
    </p>

    <h2>8. Changes to This Policy</h2>
    <p>
      We may update this policy to reflect changes in our practices. The latest version will always be available at this URL.
    </p>
  </div>
</template>

<script setup lang="ts">
useSeoMeta({ robots: 'noindex, follow' })

useHead({
  title: 'Privacy Policy',
  link: [
    { rel: 'canonical', href: useCanonical() },
  ],
})
</script>