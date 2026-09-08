# The Neural Journal — Brand Identity & Design System

## Brand Overview

**Name**: The Neural Journal  
**Tagline**: *Curated, fact-driven journalism with an Indian perspective*  
**Positioning**: An AI-powered news engine that combines machine intelligence with editorial rigor to deliver comprehensive, balanced, source-attributed journalism.

**Core Values**: Clarity · Depth · Transparency · Accuracy

---

## Visual Identity

### Color Palette (HSL)

| Role | Light Mode | Dark Mode | Usage |
|------|-----------|-----------|-------|
| **Primary** | `220 30% 15%` (deep navy, near-black) | `220 20% 85%` (soft white) | Masthead, primary buttons, key UI elements |
| **Primary Foreground** | `0 0% 100%` | `220 30% 10%` | Text on primary |
| **Secondary** | `38 95% 50%` (warm amber) | `38 95% 60%` | Accents, highlights, CTAs, "live" indicators |
| **Secondary Foreground** | `0 0% 100%` | `220 30% 10%` | Text on secondary |
| **Accent** | `210 100% 35%` (deep blue) | `210 100% 60%` | Links, focus states, secondary actions |
| **Background** | `0 0% 100%` (white) | `220 15% 6%` (deep charcoal) | Page background |
| **Foreground** | `0 0% 0%` | `220 10% 93%` | Primary text |
| **Muted** | `0 0% 92%` | `220 10% 15%` | Subtle backgrounds, dividers |
| **Muted Foreground** | `0 0% 33%` | `220 5% 63%` | Secondary text, timestamps, meta |
| **Border** | `0 0% 80%` | `220 10% 25%` | Dividers, input borders |
| **Border Strong** | `0 0% 0%` | `220 10% 93%` | High-contrast dividers (article footers) |
| **Card** | `0 0% 100%` | `220 10% 10%` | Card backgrounds |
| **Ring** | `220 30% 15%` | `220 20% 85%` | Focus rings |

### Hex Equivalents (Light Mode)
- Primary: `#1a2233` (deep navy)
- Secondary: `#f5a623` (warm amber)
- Accent: `#007aff` (deep blue)
- Background: `#ffffff`
- Foreground: `#000000`
- Muted: `#ebebeb`
- Border: `#cccccc`

### Color Usage Rules
1. **Primary (navy)** — Authority, masthead, primary CTAs, header bars
2. **Secondary (amber)** — Energy, "live" badges, breaking news, highlights — use sparingly
3. **Accent (blue)** — Links, interactive elements, focus states
4. **Never use secondary as primary background** — it's an accent only
5. **Dark mode** inverts the semantic roles (primary becomes light, background becomes dark)

---

## Typography

### Font Stack (via Google Fonts)

| Role | Font | Weights | Usage |
|------|------|---------|-------|
| **Display** | `Playfair Display` | 400, 700 | Headlines, masthead, article titles, section headers |
| **Serif (Body)** | `Source Serif 4` | 400, 600, 700 | Article body copy, long-form reading |
| **Sans / Label** | `Inter` | 400, 500, 700 | UI labels, navigation, metadata, buttons, admin panel |

### Type Scale (Tailwind + Custom)

| Element | Light | Mobile | Desktop |
|---------|-------|--------|---------|
| Masthead | `text-[2.25rem]` → `text-[3.25rem]` | 36px | 52px |
| Article H1 | `text-3xl` → `text-5xl` | 30px | 48px |
| Article H2 | `text-2xl` | 24px | 24px |
| Article H3 | `text-xl` | 20px | 20px |
| Body | `text-base` (1rem = 16px) | 16px | 17px |
| Lead/Excerpt | `text-sm` → `text-base` | 14px | 16px |
| Caption/Label | `text-[11px]` / `text-xs` | 11px | 11px |
| Navigation | `text-xs` uppercase tracking-wide | 12px | 12px |

### Typographic Principles
- **Tracking**: Display/headlines use `tracking-tight`; labels/navigation use `tracking-[0.062em]` (0.062em = 1px at 16px)
- **Line height**: Body = 1.75; Headlines = 1.15–1.25; Captions = 1.5
- **Hierarchy**: Playfair Display for *editorial voice*; Source Serif 4 for *reading comfort*; Inter for *functional UI*

---

## Logo / Wordmark

### Current State
- **Text-only wordmark**: "THE NEURAL JOURNAL" in Playfair Display, uppercase, tracking-tight
- **Nav-hat badge**: "The Neural Journal" (mixed case) + "Edition: India"
- **No standalone mark/icon exists**

### Recommended Logo System

```
Primary Lockup (Horizontal)
┌─────────────────────────────────────────┐
│  ◆  THE NEURAL JOURNAL                      │
└─────────────────────────────────────────┘
         ↑
      Optional mark
      (see below)
```

**Proposed Mark**: A minimal "quill + circuit" monogram
- Concept: Fountain pen nib (journalism) merging with a circuit node (AI)
- Style: Single-weight line, works at 16×16px (favicon)
- Color: Primary (navy) on light; Primary-foreground on dark

**Variants Needed**:
1. **Full lockup** (mark + wordmark) — header, footer, social
2. **Wordmark only** — masthead, admin login
3. **Mark only** — favicon, app icon, social avatar
4. **Monochrome** — print, single-color applications

---

## Implemented Components

### Public Frontend (Complete ✓)

| Component | Location | Status |
|-----------|----------|--------|
| **Nav-hat** (top bar) | `AppHeader.vue:36-47` | ✓ Edition label, date, brand name |
| **Masthead** | `AppHeader.vue:50-58` | ✓ Playfair Display, linked to home |
| **Category Nav** | `AppHeader.vue:61-133` | ✓ Dropdowns, active states, search overlay |
| **Hero Lead** | `HeroLead.vue` | ✓ Image, category badge, excerpt, meta |
| **Article Page** | `article/[slug].vue` | ✓ Full WSJ-style: breadcrumb, byline, hero image, body, FAQ, entities, sources |
| **Compact Cards** | `CompactArticleCard.vue` | ✓ 3 variants (default/horizontal/minimal) |
| **Headlines Rail** | `HeadlinesRail.vue` | ✓ Sidebar rail |
| **Category Sections** | `CategorySection.vue` | ✓ Section header + article grid |
| **Footer** | `AppFooter.vue` | ✓ 4-column grid, real links, copyright, builder credit |
| **Legal Pages** | `about.vue`, `privacy.vue`, `terms.vue`, `cookies.vue`, `contact.vue` | ✓ Prose styling |
| **Category Browse** | `categories.vue` | ✓ Card grid layout |
| **Search** | `search.vue` | ✓ Results layout |

### Admin Panel (Complete ✓)

| Component | Location | Status |
|-----------|----------|--------|
| **Layout** | `layouts/admin.vue` | ✓ Light slate surface, slate-900 sidebar, top bar, responsive drawer |
| **Dashboard** | `admin/index.vue` | ✓ Stats cards, recent articles, category breakdown, failures |
| **Queue Monitor** | `admin/queue.vue` | ✓ Live polling, status cards, pending jobs table, history with pagination |
| **Discovery** | `admin/discovery.vue` | ✓ Parameter form, results display, retry failed |
| **Articles/Topics/Categories/Users/Settings/Audit** | `admin/*.vue` | ✓ Standard CRUD tables |
| **Login** | `admin/login.vue` | ✓ Branded, light slate theme, shield icon |

### Admin Surface Tokens

The admin is a deliberate second surface — **not** the dark theme earlier docs described. Light slate background, white surfaces, slate-900 sidebar, `#f5a623` amber accent. Centralized as CSS vars on `:root` in `assets/css/tailwind.css` and mapped to `admin.*` colors in `tailwind.config.js`:

| Token | Value | Tailwind class |
|------|-------|----------------|
| `--admin-bg` | `#F8FAFC` (slate-50) | `bg-admin-bg` |
| `--admin-surface` | white | `bg-admin-surface` |
| `--admin-sidebar` | `#0f172a` (slate-900) | `bg-admin-sidebar` |
| `--admin-border` | slate-200 | `border-admin-border` |
| `--admin-text` | slate-900 | `text-admin-text` |
| `--admin-text-muted` | slate-500 | `text-admin-muted` |
| `--admin-accent` | `#f5a623` | `text-admin-accent` / `bg-admin-accent` |
| `--admin-accent-ink` | `#1a2233` | `text-admin-accent-ink` / `bg-admin-accent-ink` |

Form inputs/buttons use the shared `.focus-ring` utility (focus-visible ring on `--ring`). Status badges (draft/released/warning) keep their own amber-* and emerald-* utilities — only the brand accent is tokenized.

### Technical SEO (Complete ✓)

| Feature | Location |
|---------|----------|
| Title template | `nuxt.config.ts:42` (`%s — The Neural Journal`) |
| Meta description | `nuxt.config.ts:46` |
| Theme color | `nuxt.config.ts:47` (`#1a2233`) |
| Open Graph | `nuxt.config.ts:48-50` |
| Twitter Card | `nuxt.config.ts:51-52` |
| Favicon | `nuxt.config.ts:55` (`/favicon.ico`) |
| Article JSON-LD | `article/[slug].vue:42-74` (NewsArticle, BreadcrumbList, FAQPage) |
| Canonical URLs | `article/[slug].vue:37` |
| Sitemap/robots/feed | `server/routes/sitemap*.ts`, `robots.txt.ts`, `feed.xml.ts` |

---

## Missing / Recommended Assets

> **Status**: `favicon.ico` and `robots.txt` (served via `server/routes/robots.txt.ts`) now exist. The full PNG/OG suite below is still missing.

### 1. Logo & Favicon Suite (High Priority)
```
public/
├── favicon.ico              # ✓ EXISTS (32×32, 16×16)
├── favicon-16x16.png        # ✗ missing
├── favicon-32x32.png        # ✗ missing
├── apple-touch-icon.png     # ✗ missing (180×180, mark + wordmark)
├── android-chrome-192x192.png  # ✗ missing
├── android-chrome-512x512.png  # ✗ missing
├── safari-pinned-tab.svg    # ✗ missing (monochrome mark)
├── og-default.png           # ✗ missing (1200×630, article fallback)
├── og-home.png              # ✗ missing (1200×630, homepage)
└── twitter-card.png         # ✗ missing (1200×600)
```

**Design Spec for OG Default**:
- Background: Primary navy (`#1a2233`)
- Centered: Mark (white) + "The Neural Journal" (Playfair Display, white)
- Subtitle: "Curated, fact-driven journalism" (Inter, amber)
- Bottom: "theneuraljournal.com" (Inter, muted)

### 2. Email Templates (Newsletter)
Create `frontend/emails/` with MJML templates:
- `daily-digest.mjml` — Hero + 5 articles + footer
- `breaking-alert.mjml` — Single article, high urgency
- `welcome.mjml` — Onboarding
- Use same color palette, Playfair Display for headlines, Source Serif 4 for body

### 3. Print / PDF Styles
Add `@media print` to `tailwind.css`:
```css
@media print {
  .no-print { display: none !important; }
  .article-body { font-size: 11pt; line-height: 1.6; }
  h1 { font-size: 24pt; }
  h2 { font-size: 18pt; }
  a { text-decoration: none; color: inherit; }
  a::after { content: " (" attr(href) ")"; font-size: 8pt; color: #666; }
}
```

### 4. Component Style Guide (Storybook / Static Page)
Create `frontend/pages/styleguide.vue` documenting:
- Color swatches with HSL/hex/CSS vars
- Type scale with examples
- Button states (primary, secondary, ghost, destructive)
- Form inputs (default, focus, error, disabled)
- Badges (status: published/pending/failed, category tags)
- Cards (article, topic, stat)
- Tables (admin, data)
- Alerts (success, warning, error, info)
- Loading skeletons matching card shapes

### 5. Admin Polish (Medium Priority)
- **Login page**: Add mark above "The Neural Journal", subtle background pattern
- **Sidebar**: Replace "Admin" text with mark + wordmark
- **Top bar**: Add favicon in browser tab (already in nuxt.config)
- **Empty states**: Branded illustrations for "no articles", "no queue jobs"
- **Loading states**: Skeleton screens matching card layouts

### 6. Social Media Kit
- **X/Twitter header**: 1500×500 — mark left, wordmark center, tagline right
- **LinkedIn banner**: 1128×191 — similar composition
- **Profile avatar**: 400×400 — mark only, centered
- **Share preview template**: Consistent OG image generator (server-side)

### 7. Motion / Interaction Tokens
Add to `tailwind.css`:
```css
@layer base {
  :root {
    --duration-fast: 150ms;
    --duration-normal: 250ms;
    --duration-slow: 350ms;
    --ease-out: cubic-bezier(0.25, 0.46, 0.45, 0.94);
    --ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
  }
}
@layer utilities {
  .transition-fast { transition: all var(--duration-fast) var(--ease-out); }
  .transition-normal { transition: all var(--duration-normal) var(--ease-out); }
}
```

### 8. Accessibility Enhancements
- Ensure 4.5:1 contrast for all text (verify secondary amber on white = 3.1:1 ❌ → use darker amber `38 95% 40%` for body text)
- Focus visible outlines: `focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2`
- Skip link: Add `<a href="#main" class="sr-only focus:not-sr-only fixed top-4 left-4 z-50...">Skip to content</a>` in `app.vue`

---

## Brand Voice Guidelines

### Tone
- **Authoritative but accessible** — expert without jargon
- **Calm and measured** — never sensational
- **Transparent** — explain "how" and "why"
- **Indian context first** — local lens on global stories

### Writing Rules
- **Headlines**: Sentence case, specific, under 80 chars
- **Leads**: Answer who/what/where/when/why in 1-2 sentences
- **FAQs**: Anticipate reader questions; answers ≤ 50 words
- **Source attribution**: Always name the outlet; link to original
- **AI disclosure**: "This article was generated by AI from verified sources" in footer

### Do / Don't
| Do | Don't |
|----|-------|
| "India's GDP grows 7.2% in Q3" | "Economy Booms!" |
| "Sources: Reuters, The Hindu, Bloomberg" | "Sources say..." |
| "The ministry stated X; however, Y reports..." | "Experts believe..." |
| Use "we" for editorial voice | Use "I" or anthropomorphize the AI |

---

## Implementation Checklist

### Immediate (Before Launch)
- [x] `favicon.ico` + `robots.txt` (served via `server/routes/robots.txt.ts`)
- [ ] Create PNG favicon suite (16/32/180/192/512) — ICO exists, PNGs still missing
- [ ] Design and add `og-default.png` (1200×630)
- [ ] Verify contrast ratios (fix secondary amber for text)
- [ ] Add skip link in `app.vue`
- [ ] Test dark mode across all pages
- [ ] Verify OG/Twitter cards render correctly

> Note: Umami analytics and Google AdSense load only after cookie consent is accepted — see `plugins/umami.client.ts` and `plugins/adsense.client.ts`.

### Short-term (v1.1)
- [ ] Build logo mark (quill+circuit) in SVG
- [ ] Add mark to nav-hat, masthead, admin login, footer
- [ ] Create email templates (MJML)
- [ ] Add print stylesheet
- [ ] Build styleguide page (`/styleguide`)

### Medium-term (v1.2)
- [ ] Social media kit (X header, LinkedIn banner, avatar)
- [ ] Dynamic OG image generation (per article)
- [ ] Admin empty/loading state illustrations
- [ ] Motion tokens + consistent transitions
- [ ] Component library documentation

---

## File Reference Map

| File | Purpose |
|------|---------|
| `frontend/assets/css/tailwind.css` | Color tokens, base typography, utilities |
| `frontend/nuxt.config.ts` | Fonts, SEO meta, favicon, title template |
| `frontend/components/AppHeader.vue` | Masthead, nav-hat, category nav, search |
| `frontend/components/AppFooter.vue` | Footer grid, legal links, social, copyright |
| `frontend/layouts/default.vue` | Public layout wrapper |
| `frontend/layouts/admin.vue` | Admin layout (light slate surface, slate-900 sidebar) |
| `frontend/pages/article/[slug].vue` | Article template + JSON-LD |
| `frontend/components/news/*.vue` | Reusable news components |
| `frontend/pages/admin/*.vue` | Admin views |

---

## Brand Assets Needed (Design Handoff)

If working with a designer, provide this brief:

> **Project**: The Neural Journal — AI-powered news engine
> **Deliverables**:
> 1. **Logo mark** (SVG): Quill nib + circuit node, single-weight line, works at 16px
> 2. **Favicon suite** (ICO + PNG sizes above)
> 3. **OG default image** (1200×630 PNG) — navy bg, white mark+wordmark, amber tagline
> 4. **Social kit**: X header (1500×500), LinkedIn banner (1128×191), avatar (400×400)
> 5. **Admin login illustration** (optional): Subtle geometric pattern in navy/amber
> 6. **Empty state illustrations** (3): No articles, No queue jobs, No search results
>
> **Colors**: Primary `#1a2233`, Secondary `#f5a623`, Accent `#007aff`
> **Fonts**: Playfair Display (headlines), Source Serif 4 (body), Inter (UI)
> **Tone**: Editorial, authoritative, transparent, Indian-context-first