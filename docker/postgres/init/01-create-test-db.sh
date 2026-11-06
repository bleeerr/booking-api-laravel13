#!/bin/sh
set -eu

TEST_DB="${POSTGRES_TEST_DB:-booking_api_test}"

if ! psql \
    --username "$POSTGRES_USER" \
    --dbname "$POSTGRES_DB" \
    --tuples-only \
    --no-align \
    --command "SELECT 1 FROM pg_database WHERE datname = '$TEST_DB'" \
    | grep -q '^1$'; then

    psql \
        --username "$POSTGRES_USER" \
        --dbname "$POSTGRES_DB" \
        --set=ON_ERROR_STOP=1 \
        --command "CREATE DATABASE \"$TEST_DB\" OWNER \"$POSTGRES_USER\";"
fi
