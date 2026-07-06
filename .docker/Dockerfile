# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — Front-end assets (Vite + Tailwind v4)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2 — PHP application runtime (php-fpm)
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS app

# Persistent + build dependencies for the PHP extensions we need
RUN apk add --no-cache \
        bash \
        git \
        icu-libs \
        libpng \
        libjpeg-turbo \
        freetype \
        libzip \
        postgresql-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        gd \
        intl \
        zip \
        bcmath \
        pcntl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# Application source
COPY . .

# Built front-end assets from the assets stage
COPY --from=assets /app/public/build ./public/build

# Finalise autoloader + package discovery, then tidy permissions
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

# PHP + opcache configuration
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Entry point handles migrations, caching and boot
COPY .docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Stage 3 — Nginx web server (serves static assets, proxies PHP to app:9000)
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

COPY .docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Only the public directory is needed to serve static files
COPY --from=app /var/www/html/public /var/www/html/public

# Serve user uploads (public disk) from the shared storage volume mounted at runtime
RUN ln -s /var/www/html/storage/app/public /var/www/html/public/storage
