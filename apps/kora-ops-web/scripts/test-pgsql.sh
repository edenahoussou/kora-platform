#!/usr/bin/env bash
set -euo pipefail

DB_HOST="${KORA_TEST_DB_HOST:-${DB_HOST:-127.0.0.1}}"
DB_PORT="${KORA_TEST_DB_PORT:-${DB_PORT:-5432}}"
DB_USERNAME="${KORA_TEST_DB_USERNAME:-${DB_USERNAME:-kora}}"
DB_PASSWORD="${KORA_TEST_DB_PASSWORD:-${DB_PASSWORD:-}}"
DB_ADMIN_DATABASE="${KORA_TEST_DB_ADMIN_DATABASE:-postgres}"
DB_PREFIX="${KORA_TEST_DB_PREFIX:-kora_test}"
TEST_PATH="${1:-tests/Feature/KoraBus}"

if [[ -z "$DB_PASSWORD" ]]; then
    echo "KORA_TEST_DB_PASSWORD is required. Example: KORA_TEST_DB_PASSWORD='...' composer test:pgsql" >&2
    exit 1
fi

if ! command -v createdb >/dev/null 2>&1; then
    echo "createdb was not found. Install PostgreSQL client tools or run from a container that has them." >&2
    exit 1
fi

if ! command -v dropdb >/dev/null 2>&1; then
    echo "dropdb was not found. Install PostgreSQL client tools or run from a container that has them." >&2
    exit 1
fi

DB_DATABASE="${DB_PREFIX}_$(date +%s)_${RANDOM}"

cleanup() {
    PGPASSWORD="$DB_PASSWORD" dropdb \
        --if-exists \
        -h "$DB_HOST" \
        -p "$DB_PORT" \
        -U "$DB_USERNAME" \
        "$DB_DATABASE" >/dev/null 2>&1 || true
}

trap cleanup EXIT

echo "Creating temporary PostgreSQL database: $DB_DATABASE"

if ! PGPASSWORD="$DB_PASSWORD" createdb \
    -h "$DB_HOST" \
    -p "$DB_PORT" \
    -U "$DB_USERNAME" \
    "$DB_DATABASE"; then
    cat >&2 <<MSG
Unable to create temporary database "$DB_DATABASE".
Ensure PostgreSQL is reachable and role "$DB_USERNAME" has CREATEDB:
  ALTER ROLE $DB_USERNAME CREATEDB;
MSG
    exit 1
fi

echo "Running migrations and seeders on $DB_DATABASE"

DB_CONNECTION=pgsql \
DB_HOST="$DB_HOST" \
DB_PORT="$DB_PORT" \
DB_DATABASE="$DB_DATABASE" \
DB_USERNAME="$DB_USERNAME" \
DB_PASSWORD="$DB_PASSWORD" \
CACHE_STORE=array \
QUEUE_CONNECTION=sync \
SESSION_DRIVER=array \
php artisan migrate:fresh --seed --force

echo "Running PostgreSQL-backed tests: $TEST_PATH"

DB_CONNECTION=pgsql \
DB_HOST="$DB_HOST" \
DB_PORT="$DB_PORT" \
DB_DATABASE="$DB_DATABASE" \
DB_USERNAME="$DB_USERNAME" \
DB_PASSWORD="$DB_PASSWORD" \
CACHE_STORE=array \
QUEUE_CONNECTION=sync \
SESSION_DRIVER=array \
php artisan test "$TEST_PATH"

echo "PostgreSQL test workflow passed."
