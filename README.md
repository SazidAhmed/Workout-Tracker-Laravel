# Fit Backend (Laravel API)

This repository contains the API backend for the Fit platform.

The frontend is a separate Vue app and should be run from the sibling project folder.

## Services and default ports

- Backend API: http://localhost:8001
- MySQL: localhost:3308
- phpMyAdmin: http://localhost:8081
- Frontend app (separate repo): http://localhost:5173

## Prerequisites

- Docker Desktop (with Docker Compose)
- Node.js 20+ and npm (for the separate frontend app)

## Backend quickstart (Docker)

From this folder:

```bash
docker compose up -d --build
docker compose ps
```

Run migrations and seed demo data:

```bash
docker compose exec -T app php artisan migrate:fresh --seed --force
```

Clear caches (safe to run anytime during local development):

```bash
docker compose exec app php artisan optimize:clear
```

## Frontend quickstart (separate project)

Open a second terminal and run these commands from the frontend repository:

```bash
cd h:/Vue/Fit-frontend
cp .env.example .env
npm install
npm run dev
```

The frontend uses:

- VITE_API_BASE_URL=/api
- VITE_API_PROXY_TARGET=http://localhost:8001

With these defaults, requests from http://localhost:5173/api are proxied to the backend container at http://localhost:8001/api.

## Verify everything is running

Health check:

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8001/up
```

Login check:

```bash
curl -s -X POST http://localhost:8001/api/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

Seeded admin credentials:

- Email: admin@example.com
- Password: password

## Common local commands

Stop services:

```bash
docker compose down
```

View backend logs:

```bash
docker compose logs -f app
```

Recreate only database data:

```bash
docker compose exec -T app php artisan migrate:fresh --seed --force
```

## Troubleshooting

- If login fails from the frontend, confirm the backend is up on port 8001 and restart the frontend dev server.
- If you see stale behavior after config changes, run optimize:clear in the backend container.
- If containers fail to start due to name conflicts, remove old containers and run docker compose up again.
