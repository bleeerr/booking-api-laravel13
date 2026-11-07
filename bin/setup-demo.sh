#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

fail() {
    printf '[setup] Error: %s\n' "$1" >&2
    exit 1
}

command -v docker >/dev/null 2>&1 || fail 'Docker is required.'
docker compose version >/dev/null 2>&1 || fail 'Docker Compose plugin is required.'
docker info >/dev/null 2>&1 || fail 'Docker daemon is not running or is inaccessible.'

# This demo Compose file publishes ports 5433 (PostgreSQL) and 8080 (HTTP).
# Do not stop other projects or edit their configuration to free the ports.
check_port() {
    local port="$1" service="$2" container owner
    local own_container
    own_container="$(docker compose ps -q "$service")"

    while IFS= read -r container; do
        [[ -n "$container" ]] || continue
        if [[ -n "$own_container" && "$(docker inspect --format '{{.Id}}' "$container")" == "$own_container" ]]; then
            continue
        fi
        owner="$(docker inspect --format '{{.Name}}' "$container")"
        fail "Port $port is already published by $owner. Stop that project first, then rerun this script."
    done < <(docker ps --filter "publish=$port" --format '{{.ID}}')
}

check_port 5433 postgres
check_port 8080 nginx

if [[ ! -f .env ]]; then
    cp .env.example .env
    echo '[setup] Created .env from .env.example'
else
    echo '[setup] Reusing existing .env'
fi

echo '[setup] Building and starting database, Redis and app...'
docker compose up -d --build postgres redis app

# A failed earlier Compose start (e.g. a published port conflict) can leave
# PostgreSQL running without a network attachment. Recreate only that service.
check_postgres_dns() {
    docker compose exec -T app php -r 'exit(gethostbyname("postgres") === "postgres" ? 1 : 0);' >/dev/null 2>&1
}

if ! check_postgres_dns; then
    postgres_container="$(docker compose ps -q postgres)"
    [[ -n "$postgres_container" ]] || fail 'PostgreSQL container was not created.'
    attached_networks="$(docker inspect --format '{{len .NetworkSettings.Networks}}' "$postgres_container")"
    if [[ "$attached_networks" == 0 ]]; then
        echo '[setup] PostgreSQL lost its Docker network; recreating its container (data volume preserved)...'
        docker compose up -d --force-recreate --no-deps postgres
    fi
fi

# Do not run Composer/migrations before the database is addressable from app.
for attempt in 1 2 3 4 5; do
    if check_postgres_dns; then
        break
    fi
    if [[ "$attempt" == 5 ]]; then
        fail 'App cannot resolve postgres on the Docker network. Check docker compose ps -a and docker network inspect.'
    fi
    sleep 2
done

echo '[setup] Installing Composer dependencies...'
docker compose exec -T app composer install --no-interaction --prefer-dist --no-progress

if ! grep -Eq '^APP_KEY=.+$' .env; then
    echo '[setup] Generating application key...'
    docker compose exec -T app php artisan key:generate --force
fi

echo '[setup] Applying database migrations...'
docker compose exec -T app php artisan migrate --force

# Seed only on an empty users table. Never run migrate:fresh automatically.
# Query PostgreSQL directly; Tinker/PsySH requires a writable configuration directory.
echo '[setup] Checking demo data...'
user_count="$(docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -tAc "SELECT COUNT(*) FROM users"')"

# The fresh passwords only exist in the seeder output. Keep them in memory for
# this run so that they can be displayed again at the end of setup.
demo_seeded=false
admin_password=''
user_password=''

if [[ "$user_count" == "0" ]]; then
    echo '[setup] Empty user table; generating demo accounts and data.'
    if seed_output="$(docker compose exec -T app php artisan db:seed --force 2>&1)"; then
        printf '%s\n' "$seed_output"
    else
        printf '%s\n' "$seed_output" >&2
        fail 'Demo seeding failed.'
    fi

    # UserSeeder prints these exact prefixes with raw console output. Preserve
    # the complete password, including punctuation and any embedded | symbol.
    admin_prefix='admin | admin@example.com | '
    user_prefix='user | user@example.com | '
    while IFS= read -r line; do
        case "$line" in
            "$admin_prefix"*) admin_password="${line#"$admin_prefix"}" ;;
            "$user_prefix"*) user_password="${line#"$user_prefix"}" ;;
        esac
    done <<< "$seed_output"
    unset seed_output
    if [[ -n "$admin_password" && -n "$user_password" ]]; then
        demo_seeded=true
    else
        echo '[setup] Warning: seed completed, but demo credentials could not be parsed.' >&2
    fi
elif [[ "$user_count" =~ ^[0-9]+$ ]]; then
    echo '[setup] Existing users detected; skipping demo seeding (no data will be replaced).'
else
    fail "Unable to determine user count (got: $user_count); refusing to seed."
fi

echo '[setup] Starting Nginx and email queue worker...'
docker compose up -d nginx queue

cat <<'END_MESSAGE'

[setup] Done.
API:        http://localhost:8080/api/v1
Workspaces: http://localhost:8080/api/v1/workspaces
END_MESSAGE

# ANSI formatting is enabled only for an interactive terminal.
green=''
reset=''
if [[ -t 1 && -z "${NO_COLOR:-}" && "${TERM:-}" != 'dumb' ]]; then
    green=$'\033[1;32m'
    reset=$'\033[0m'
fi

if [[ "$demo_seeded" == true ]]; then
    printf '\n%sDemo credentials (save these passwords):%s\n' "$green" "$reset"
    printf '%s  ADMIN  admin@example.com  |  %s%s\n' "$green" "$admin_password" "$reset"
    printf '%s  USER   user@example.com   |  %s%s\n' "$green" "$user_password" "$reset"
    printf '\n[setup] Passwords are shown only during initial seeding.\n'
else
    cat <<'END_MESSAGE'

Demo credentials:
  Demo passwords were generated during a previous setup and cannot be recovered.
  Existing user accounts have NOT been modified.

  To generate new demo credentials by resetting ALL demo database tables:
    docker compose exec app php artisan migrate:fresh --seed

  WARNING: This deletes all data in the current demo database.
  Use only with the disposable demo environment.
END_MESSAGE
fi

cat <<'END_MESSAGE'

Import docs/postman/Coworking-Booking-API.postman_collection.json into Postman.
Stop: docker compose down
Checks: docker compose exec app composer check
END_MESSAGE
