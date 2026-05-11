#!/bin/sh
set -e

# Cache framework artifacts for fast cold starts.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Apply pending migrations. --force skips the confirmation prompt.
php artisan migrate --force

# Seed the 4 teams. TeamSeeder.updateOrCreate makes this idempotent —
# safe to run on every container start, whether first deploy or restart.
php artisan db:seed --force

# Hand off to supervisord, which runs nginx + php-fpm side by side.
exec /usr/bin/supervisord -c /etc/supervisord.conf
