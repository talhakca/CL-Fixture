# Development image for the Vue/Nx web app.
# Build context is the workspace root so Nx can see all projects.
# Source is bind-mounted at runtime; node_modules rides on a named volume.

# Node 22 — orval (SDK generator) declares engines.node >=22.18.0.
FROM node:22-alpine

WORKDIR /workspace

# Native build deps for esbuild/vite/etc.
RUN apk add --no-cache git python3 make g++

# Copy package manifests early so the install layer is cached separately from source.
COPY package.json package-lock.json ./
COPY nx.json tsconfig.base.json tsconfig.json ./
COPY apps/web/package.json ./apps/web/
COPY libs/ui/package.json ./libs/ui/
COPY libs/api-sdk/package.json ./libs/api-sdk/

# Using `npm install` instead of `npm ci` because the workspace lockfile has
# drifted from package.json (missing @emnapi/* transitives). Regenerate the
# lockfile locally and switch back to `npm ci` for stricter builds.
RUN npm install

EXPOSE 4200

CMD ["npx", "nx", "run", "web:dev", "--host", "0.0.0.0", "--port", "4200"]
