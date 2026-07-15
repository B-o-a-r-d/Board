# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — Front-end assets (Vite + Tailwind v4)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

# Vite bakes VITE_* into the JS bundle at build time. Provide the Reverb client
# config as build args so the compiled Echo config points at the prod WebSocket.
ARG VITE_REVERB_APP_KEY=""
ARG VITE_REVERB_HOST=""
ARG VITE_REVERB_PORT="443"
ARG VITE_REVERB_SCHEME="https"
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

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

# Optional first-party Marketplace Power-Up (runtime plugin installer). Off by
# default so the base image ships no runtime-install code; set
# INSTALL_MARKETPLACE=true (Dokploy → Environment) to bake it in — admins can then
# install Power-Ups at runtime from /marketplace (still gated by a master switch).
ARG INSTALL_MARKETPLACE=false

# GitHub token used ONLY to fetch board/marketplace at build time. Avoids the
# anonymous GitHub API rate limit (60/h) that breaks repeated builds, and lets a
# private plugin repo resolve. A minimal / no-scope PAT is enough (repos are public).
# It is unset right after use so it is not kept in the final image layer.
ARG GITHUB_TOKEN=

# Finalise autoloader + package discovery, then tidy permissions
RUN if [ "$INSTALL_MARKETPLACE" = "true" ]; then \
        echo "[build] Enabling board/marketplace…"; \
        git config --global url."https://github.com/".insteadOf "git@github.com:"; \
        if [ -n "$GITHUB_TOKEN" ]; then composer config --global github-oauth.github.com "$GITHUB_TOKEN"; fi; \
        composer require board/marketplace:^0.1 --no-scripts --no-interaction --prefer-dist --update-no-dev; \
        composer config --global --unset github-oauth.github.com || true; \
    fi \
    && composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

# PHP + opcache configuration
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# PHP-FPM pool tuning (raises the default 5-worker cap; see www.conf).
COPY .docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

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
