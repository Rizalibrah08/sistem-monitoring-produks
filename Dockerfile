# syntax=docker/dockerfile:1.7

# Image aplikasi Aisha Monitor. Target:
#   app  PHP-FPM untuk aplikasi, queue worker, dan scheduler
#   web  Nginx yang menyajikan aset publik dan meneruskan PHP ke app

FROM composer:2 AS composer

FROM php:8.5-fpm-bookworm AS php-base

ENV TZ=Asia/Jakarta

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_mysql redis intl bcmath zip opcache pcntl \
    && apt-get update \
    && apt-get install -y --no-install-recommends tzdata \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-aisha.ini

WORKDIR /var/www/html

FROM php-base AS vendor

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

FROM node:24-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY vite.config.js tsconfig.json ./
COPY resources resources
COPY public public
RUN npm run build

FROM php-base AS app

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=vendor /var/www/html/vendor vendor
COPY . .
COPY --from=assets /app/public/build public/build
COPY docker/php/entrypoint.sh /usr/local/bin/aisha-entrypoint

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
    && chmod +x /usr/local/bin/aisha-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

ENTRYPOINT ["aisha-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.28-alpine AS web

ENV TZ=Asia/Jakarta

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
