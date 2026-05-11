# Development image for the Laravel API.
# Source is bind-mounted at runtime; composer deps live in a named volume.
# Used only by docker/compose.yml — production uses docker/api.Dockerfile.

FROM php:8.3-cli-alpine

# System deps for PHP extensions we need.
RUN apk add --no-cache \
      git \
      curl \
      libzip-dev \
      oniguruma-dev \
      icu-dev \
      postgresql-dev \
      $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql zip intl pcntl \
    && apk del $PHPIZE_DEPS \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
