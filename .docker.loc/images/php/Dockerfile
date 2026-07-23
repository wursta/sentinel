ARG PHP_VERSION=7.4

FROM php:${PHP_VERSION}-alpine

RUN apk add --no-cache git unzip curl-dev \
    && docker-php-ext-install curl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["php", "-v"]
