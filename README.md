# Fit Frontend (Vue 3 + Vite)

This repository contains the standalone frontend for the Fit platform.

It connects to the Laravel API backend through a Vite dev proxy.

## Prerequisites

- Node.js 20+
- npm
- Backend API running at http://localhost:8001

## Quickstart

From this folder:

```bash
cp .env.example .env
npm install
npm run dev
```

Open:

- http://localhost:5173

## Environment

Default local values in .env.example:

```env
VITE_API_BASE_URL=/api
VITE_API_PROXY_TARGET=http://localhost:8001
```

What this means:

- Frontend calls /api/* (same origin from the browser point of view).
- Vite forwards those requests to the backend target.
- You do not need to hardcode backend URLs in the app code for local development.

## Scripts

- npm run dev: Start development server
- npm run build: Build production assets
- npm run preview: Preview production build locally
- npm run test: Run tests in watch mode
- npm run test:run: Run tests once

## Backend startup reference

In a separate terminal, from the backend repository:

```bash
cd h:/laragon/www/Fit-Backend
docker compose up -d --build
docker compose exec -T app php artisan migrate:fresh --seed --force
```

## Seeded login for local testing

- Email: admin@example.com
- Password: password

## Troubleshooting

- 404 on /api/auth/login from port 5173: restart npm run dev so Vite reloads proxy config.
- 419 CSRF token mismatch: ensure backend is running with API-only middleware config and clear backend cache.
- Network errors: verify backend is reachable at http://localhost:8001/up.
