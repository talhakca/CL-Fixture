# Production image for the Laravel API, targeting Railway.
# Build context is the repo root so we can COPY both apps/api/ and docker/api/.

# ---- Stage 1: composer install ----
FROM php:8.4-cli-alpine AS composer

RUN apk add --no-cache git unzip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY apps/api/composer.json apps/api/composer.lock ./

RUN composer install \
      --no-dev \
      --optimize-autoloader \
      --no-scripts \
      --no-interaction \
      --prefer-dist


# ---- Stage 2: runtime ----
FROM php:8.4-fpm-alpine

# Runtime system deps (kept) + build deps (added then removed in same layer).
RUN apk add --no-cache \
      nginx \
      supervisor \
      postgresql-client \
      libzip \
      oniguruma \
      icu-libs \
      libpq \
    && apk add --no-cache --virtual .build-deps \
      $PHPIZE_DEPS \
      libzip-dev \
      oniguruma-dev \
      icu-dev \
      postgresql-dev \
    && docker-php-ext-install pdo_pgsql zip intl pcntl opcache \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/*

# Production php.ini overrides.
COPY docker/api/php-production.ini /usr/local/etc/php/conf.d/zz-production.ini

# nginx + php-fpm + supervisor configs.
COPY docker/api/nginx.conf /etc/nginx/nginx.conf
COPY docker/api/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/api/supervisord.conf /etc/supervisord.conf

# Composer vendor from stage 1.
COPY --from=composer /app/vendor /app/vendor

# Application source. .dockerignore keeps node_modules, vendor, .env out.
COPY apps/api /app

# Storage and bootstrap/cache must be writable by www-data.
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Entrypoint runs migrate + seed then hands off to supervisord.
COPY docker/api/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /app

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
