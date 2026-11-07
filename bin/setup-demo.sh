#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if ! command -v docker >/dev/null 2>&1; then
    echo 'Error: Docker is required.' >&2
    exit 1
fi
if ! docker compose version >/dev/null 2>&1; then
    echo 'Error: Docker Compose plugin is required.' >&2
    exit 1
fi
if ! docker info >/dev/null 2>&1; then
    echo 'Error: Docker daemon is not running or is inaccessible.' >&2
    exit 1
fi

if [[ ! -f .env ]]; then
    cp .env.example .env
    echo '[setup] Created .env from .env.example'
else
    echo '[setup] Reusing existing .env'
fi

# Docker services rely on the checked-in .env.example PostgreSQL/Redis defaults.
echo '[setup] Building and starting database, Redis and app...'
docker compose up -d --build postgres redis app

echo '[setup] Installing Composer dependencies...'
docker compose exec -T app composer install --no-interaction --prefer-dist --no-progress

if ! grep -Eq '^APP_KEY=.+$' .env; then
    echo '[setup] Generating application key...'
    docker compose exec -T app php artisan key:generate --force
fi

echo '[setup] Applying database migrations...'
docker compose exec -T app php artisan migrate --force

# Seed only on an empty users table. Never run migrate:fresh automatically.
# Tinker comes with this project's composer.json (laravel/tinker).
user_state="$(docker compose exec -T app php artisan tinker --execute='echo \App\Models\User::query()->exists() ? "EXISTING_USERS" : "EMPTY_USERS";' 2>&1)"
if [[ "$user_state" == *EMPTY_USERS* ]]; then
    echo '[setup] Empty user table; generating demo accounts and data.'
    echo '[setup] Save the following randomly generated credentials:'
    docker compose exec -T app php artisan db:seed --force
elif [[ "$user_state" == *EXISTING_USERS* ]]; then
    echo '[setup] Existing users detected; skipping demo seeding (no data will be replaced).'
else
    echo '[setup] Unable to verify whether demo users already exist; refusing to seed.' >&2
    printf '%s\n' "$user_state" >&2
    exit 1
fi

echo '[setup] Starting Nginx and email queue worker...'
docker compose up -d nginx queue

cat <<'EOF'

[setup] Done.
API:        http://localhost:8080/api/v1
Workspaces: http://localhost:8080/api/v1/workspaces

Demo logins (passwords printed only during initial seeding):
  admin@example.com
  user@example.com

Import docs/postman/Coworking-Booking-API.postman_collection.json into Postman.
Stop: docker compose down
Checks: docker compose exec app composer check
EOF
