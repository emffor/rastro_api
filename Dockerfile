FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd zip

# Install Redis extension
RUN pecl install redis xdebug && docker-php-ext-enable redis xdebug

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Xdebug configuration
COPY ./docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Set working directory
WORKDIR /var/www

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u 1000 -d /home/app app
RUN mkdir -p /home/app/.composer && \
    chown -R app:app /home/app

USER app
