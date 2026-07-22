FROM php:7.4-alpine

RUN apk add --no-cache git unzip curl-dev \
    && docker-php-ext-install curl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
