FROM php:8.2-cli

# Install system dependencies including PDF generation libraries
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    libfreetype6 \
    libfontconfig1 \
    fontconfig \
    fonts-dejavu-core

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Create storage directories and set permissions
RUN mkdir -p storage/fonts storage/framework/{cache,sessions,views} storage/logs \
    && chmod -R 775 storage \
    && chown -R www-data:www-data storage

EXPOSE $PORT

# Start server - clear cache first, then migrate, seed, and serve
CMD php artisan config:clear && php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=$PORT
