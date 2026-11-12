#!/bin/sh
# Container start-up: apply any new migrations, warm the caches, then start
# whatever the container was asked to run (the web server by default, or the
# queue worker / scheduler when compose passes a command).
# `set -e` stops everything if a step fails, so a broken migration never
# serves traffic.
#
# Only the web container runs migrations (the default). Compose sets
# RUN_MIGRATIONS=false on the queue worker and the scheduler, which also wait
# for the web container to be healthy before they start.
set -e

cd /app

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

# Each container has its own filesystem, so each warms its own caches.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

exec docker-php-entrypoint "$@"
