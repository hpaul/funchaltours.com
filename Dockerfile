# Stage 1: Install PHP dependencies
FROM composer:2 AS composer-build

WORKDIR /app
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

# Stage 2: Build frontend assets (needs vendor/ for Tailwind to scan Peak views)
FROM node:22-alpine AS node-build

WORKDIR /app
COPY package.json package-lock.json* ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci
COPY . .
COPY --from=composer-build /app/vendor ./vendor
RUN npm run build

# Stage 3: Production image
FROM serversideup/php:8.5-frankenphp

USER root

# Install ImageMagick with HEIF/HEIC, WebP, and AVIF support, then clean up dev headers
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update \
    && apt-get install -y --no-install-recommends \
       libmagickwand-dev libheif-dev libwebp-dev libde265-dev git openssh-client \
    && install-php-extensions bcmath pdo_sqlite imagick gd exif redis \
    && apt-get purge -y \
       libmagickwand-dev libheif-dev libwebp-dev libde265-dev \
    && apt-get autoremove -y

USER www-data

# Copy application code
COPY --chown=www-data:www-data . /var/www/html

# Copy composer dependencies from build stage
COPY --chown=www-data:www-data --from=composer-build /app/vendor /var/www/html/vendor

# Copy built frontend assets from node stage
COPY --chown=www-data:www-data --from=node-build /app/public/_build /var/www/html/public/_build

ENV HOME=/var/www

# Create required Laravel directories and rebuild package manifest without dev dependencies
RUN mkdir -p /var/www/.ssh \
    && printf "Host github.com\n  HostName github.com\n  User git\n  IdentityFile /var/www/.ssh/id_ed25519\n  IdentitiesOnly yes\n  StrictHostKeyChecking accept-new\n" > /var/www/.ssh/config \
    && chmod 700 /var/www/.ssh \
    && chmod 600 /var/www/.ssh/config \
    && git config --global --add safe.directory /var/www/html

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/forms bootstrap/cache \
    && rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi

COPY --chown=root:root --chmod=755 docker/10-setup-ssh-key.sh /etc/entrypoint.d/10-setup-ssh-key.sh
COPY --chown=root:root --chmod=755 docker/20-content-sync.sh /usr/local/bin/content-sync-once.sh
COPY --chown=root:root --chmod=755 docker/21-start-content-sync.sh /etc/entrypoint.d/21-start-content-sync.sh
