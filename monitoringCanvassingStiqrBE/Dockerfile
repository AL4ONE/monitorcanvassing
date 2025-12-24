# Use PHP 8.3
FROM nixpacks/php:8.3

WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies (production only, no dev dependencies)
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# Copy application files
COPY . .

# Cache configuration
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Expose port
EXPOSE 8000

# Start server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

