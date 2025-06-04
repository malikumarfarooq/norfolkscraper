FROM php:8.2-apache

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev libpq-dev zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd pdo pgsql pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Apache configuration (FIXED SYNTAX)
RUN a2enmod rewrite && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/public|' /etc/apache2/sites-available/000-default.conf && \
    { \
        echo '<Directory /var/www/public>'; \
        echo '  AllowOverride All'; \
        echo '  Require all granted'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/laravel.conf && \
    a2enconf laravel

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Permissions
RUN mkdir -p /var/www/storage/framework/{cache,views,sessions} \
    && mkdir -p /var/www/storage/logs \
    && chown -R www-data:www-data /var/www/storage \
    && chmod -R 775 /var/www/storage

WORKDIR /var/www

# Install dependencies first
COPY composer.json composer.lock .
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

# Copy app
COPY . .

# Post-install
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && php artisan config:cache \
    && php artisan view:cache

# Final permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www \
    && chmod -R 775 /var/www/bootstrap/cache

EXPOSE 80
CMD ["apache2-foreground"]
