<template>
  <div class="prose prose-slate max-w-none">
    <h1>About The Neural Journal</h1>

    <p class="lead">
      The Neural Journal is an AI-powered news engine delivering curated, fact-driven journalism
      with an Indian perspective. We combine machine intelligence with editorial rigor to
      cover the stories that matter — from politics and business to science and culture.
    </p>

    <h2>Our Mission</h2>
    <p>
      In an era of information overload, we believe in clarity, depth, and context.
      Our AI engine continuously discovers, verifies, and synthesizes news from hundreds
      of sources, producing original articles that are comprehensive, balanced, and
      accessible. Every story is generated with a mandatory FAQ section to anticipate
      reader questions.
    </p>

    <h2>How It Works</h2>
    <ol>
      <li><strong>Discovery</strong> — Our engine scans Google News RSS and Brave Search
        across 20 Indian states and 5 global regions every hour, identifying emerging
        stories from multiple independent sources.</li>
      <li><strong>Verification</strong> — Topics are only accepted when corroborated by
        at least two distinct sources, reducing single-source bias.</li>
      <li><strong>Generation</strong> — A large language model (Gemma 4 31B) writes each
        article with structured sections, entity context, and source attribution.</li>
      <li><strong>Quality Gate</strong> — Every article passes automated checks for
        factual consistency, source diversity, and readability before publication.</li>
    </ol>

    <h2>Editorial Standards</h2>
    <p>
      We do not publish opinion pieces, editorials, or unverified claims. All articles
      are grounded in source material. Our AI does not invent facts — it synthesizes
      what has already been reported by credible outlets. We disclose our methodology
      openly and welcome scrutiny.
    </p>

    <h2>Coverage</h2>
    <p>
      Our primary focus is India — all 28 states and 8 union territories — with dedicated
      sections for National, World, Business, Technology, Sports, Entertainment, Lifestyle,
      and Science &amp; Education. We also run a parallel global discovery pipeline covering
      South Asia, Asia-Pacific, Americas, Europe, and the Middle East &amp; Africa.
    </p>

    <h2>Contact</h2>
    <p>
      For inquiries, corrections, or partnership opportunities, reach us at
      <a href="mailto:hello@theneuraljournal.com">hello@theneuraljournal.com</a>.
    </p>
  </div>
</template>

<script setup lang="ts">
useHead({
  title: 'About',
  meta: [
    { name: 'description', content: 'The Neural Journal is an AI-powered newsroom by Technioz — see how we discover, verify, and publish India-focused journalism automatically.' },
  ],
  link: [
    { rel: 'canonical', href: useCanonical() },
  ],
})
usePageSeo('About', 'The Neural Journal is an AI-powered newsroom by Technioz — see how we discover, verify, and publish India-focused journalism automatically.')
</script>
