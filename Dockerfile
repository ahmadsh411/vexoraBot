FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    unzip \
    libpng-dev \
    libzip-dev \
    zip \
    icu-dev \
    oniguruma-dev

# Install PHP extensions needed by Laravel
RUN docker-php-ext-install pdo pdo_mysql bcmath gd zip mbstring intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy project files
COPY . .

# Set permissions & run composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Expose port 80
EXPOSE 80
CMD php artisan config:clear && php artisan route:clear && php artisan migrate --force && php artisan nutgram:hook:remove && (pkill -f "nutgram:run" || true) && sleep 2 && php artisan nutgram:run & php artisan serve --host=0.0.0.0 --port=80
