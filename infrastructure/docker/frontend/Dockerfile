# Vue 3 + Vite development server.
# Production builds should use a multi-stage build and serve the compiled
# assets through Nginx or a static host.
FROM node:22-alpine

WORKDIR /app

# package.json / lockfile are mounted in development; install runs against them.
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
