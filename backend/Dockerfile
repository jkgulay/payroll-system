FROM php:8.2-cli

# Install system dependencies including PDF generation libraries
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    libfreetype6-dev \
    libfontconfig1 \
    fontconfig \
    fonts-dejavu-core \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    pkg-config

# Install PHP extensions including GD for image processing in PDFs
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Create storage directories and set permissions
RUN mkdir -p storage/fonts storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE $PORT

# Start server - clear cache, optimize config, then migrate, seed, and serve
CMD php artisan config:clear && \
    php artisan config:cache && \
    php artisan migrate --force && \
    php artisan db:seed --force && \
    php artisan serve --host=0.0.0.0 --port=$PORT
