# Vue 3 + Vite development server.
# Production builds should use a multi-stage build and serve the compiled
# assets through Nginx or a static host.
FROM node:22-alpine

WORKDIR /app

# Keep dependencies available when the source bind mount is used in development.
COPY frontend/package.json frontend/package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    NODE_OPTIONS="--dns-result-order=ipv4first" \
    npm ci \
      --no-audit \
      --no-fund \
      --prefer-offline \
      --fetch-retries=5 \
      --fetch-retry-mintimeout=20000 \
      --fetch-retry-maxtimeout=120000 \
      --maxsockets=5

EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
