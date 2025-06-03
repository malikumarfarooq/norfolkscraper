FROM php:8.2-apache

# Install system dependencies + PostgreSQL
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd pdo pgsql pdo_pgsql  # ← Add these

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ... rest of your Dockerfile remains the same ...
