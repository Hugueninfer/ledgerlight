# syntax=docker/dockerfile:1

# ---- base: node + pnpm via corepack ----
FROM node:22-alpine AS base

ENV PNPM_HOME=/pnpm \
    PATH=/pnpm:$PATH \
    COREPACK_HOME=/usr/local/share/corepack \
    npm_config_store_dir=/pnpm/store \
    CI=true
# Bake pnpm into the image (keep the version in sync with "packageManager" in web/package.json)
# so containers never download it at start-up.
RUN corepack enable && corepack prepare pnpm@9.15.9 --activate \
    && chmod -R a+rX /usr/local/share/corepack

WORKDIR /app

# ---- dev: non-root user matching the host uid/gid so bind mounts stay owned by the host user ----
# node_modules and .nuxt live in named volumes (see compose.yaml): alpine needs musl binaries,
# which must not mix with a glibc host install.
FROM base AS dev

ARG UID=1000
ARG GID=1000

# node:alpine ships a "node" user/group with uid/gid 1000; replace it with one matching the host.
RUN (deluser --remove-home node 2>/dev/null || true) \
    && (delgroup node 2>/dev/null || true) \
    && addgroup -g "${GID}" app \
    && adduser -D -u "${UID}" -G app -h /home/app app \
    && mkdir -p /pnpm/store /app/node_modules /app/.nuxt \
    && chown -R app:app /pnpm /app

USER app
EXPOSE 3000
CMD ["sh", "-c", "pnpm install --frozen-lockfile && exec pnpm exec nuxt dev --host 0.0.0.0"]

# ---- build: install and compile ----
FROM base AS build

COPY web/package.json web/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile
COPY web/ ./
RUN pnpm exec nuxt build

# ---- prod: standalone Nitro output only ----
FROM node:22-alpine AS prod

ENV NODE_ENV=production \
    HOST=0.0.0.0 \
    PORT=3000

WORKDIR /app
COPY --from=build --chown=node:node /app/.output ./.output

USER node
EXPOSE 3000
CMD ["node", ".output/server/index.mjs"]
