# ============================================================
# 🏗️ TAHAP 1: BUILD STAGE - Composer Dependencies
# ------------------------------------------------------------
# Tujuan: Install composer dependencies
# ============================================================
FROM composer:2 AS composer-builder

WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies respecting composer.lock (deterministic builds)
# Note: --ignore-platform-reqs is safe here because extensions are installed in production stage
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --ignore-platform-reqs


# ============================================================
# 🏗️ TAHAP 2: BUILD STAGE - Node Assets
# ------------------------------------------------------------
# Tujuan: Membangun asset frontend Laravel Vite
# ============================================================
FROM node:20.17.0-alpine AS node-builder

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm install --legacy-peer-deps

# Copy source code
COPY . .

# Build assets
RUN npm run build


# ============================================================
# 🚀 TAHAP 3: PRODUCTION STAGE - PHP-FPM + NGINX
# ------------------------------------------------------------
# Tujuan: Menjalankan aplikasi Laravel dengan PHP-FPM dan NGINX
# ============================================================
FROM php:8.3-fpm-alpine

# Install system dependencies dan PHP extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip xml dom

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY --chown=www-data:www-data . .

# Copy composer dependencies dari builder
COPY --from=composer-builder --chown=www-data:www-data /app/vendor ./vendor

# Install composer for production (needed for autoload regeneration)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Regenerate autoload files in production environment
RUN composer dump-autoload --optimize --no-dev

# Copy built assets dari node builder
COPY --from=node-builder --chown=www-data:www-data /app/public/build ./public/build

# Create required directories and set permissions
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && mkdir -p /var/log/supervisor \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy nginx configuration
COPY docker-config/nginx.conf /etc/nginx/http.d/default.conf

# Copy supervisor configuration
RUN mkdir -p /etc/supervisor/conf.d
COPY docker-config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script
COPY docker-config/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Expose port 80
EXPOSE 80

# Use entrypoint script
ENTRYPOINT ["/entrypoint.sh"]

# Run supervisor to manage nginx and php-fpm
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
