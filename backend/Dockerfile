FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

EXPOSE $PORT

# Start server - clear cache first, then migrate, seed, and serve
CMD php artisan config:clear && php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=$PORT
