#!/bin/sh
# Dev entrypoint for the Laravel API container.
# Populates the bind-mounted vendor volume on first start, then hands off
# to whatever was passed as CMD (php artisan serve by default).

set -e

if [ ! -f vendor/autoload.php ]; then
  echo "[dev-entrypoint] vendor/ missing, running composer install..."
  composer install --no-interaction --no-progress
fi

exec "$@"
