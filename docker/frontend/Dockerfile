FROM node:22-alpine AS base
WORKDIR /app

# ---------- Dependencies ----------
FROM base AS deps
COPY frontend/package.json frontend/package-lock.json ./
# Skip postinstall (nuxt prepare) — it needs source files not yet available
RUN npm install

# ---------- Build ----------
FROM base AS build
COPY --from=deps /app/node_modules ./node_modules
COPY frontend/ .

# Build-time default: backend URL used during SSR rendering in nuxt.config.ts
ENV BACKEND_API_BASE=http://backend:8000

# Run nuxt prepare (generates .nuxt types) then build
RUN npx nuxt prepare && npm run build

# ---------- Production ----------
FROM base AS production

# Copy only the built Nitro output
COPY --from=build /app/.output ./.output

ENV NODE_ENV=production
ENV HOST=0.0.0.0
ENV PORT=3000

# Nuxt runtime override: NUXT_ prefix maps to runtimeConfig.backendApiBase
ENV NUXT_BACKEND_API_BASE=http://backend:8000

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]
