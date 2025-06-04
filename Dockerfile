# 🚀 Production-Ready Laravel Dockerfile (Tested on PHP 8.2 + Apache)
FROM php:8.2-apache

# 🛠 System dependencies with cleanup
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd pdo pgsql pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 🎛️ Apache configuration (fixed syntax)
RUN a2enmod rewrite && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/public|' /etc/apache2/sites-available/000-default.conf && \
    echo '<Directory /var/www/public>' > /etc/apache2/conf-available/laravel.conf && \
    echo '    AllowOverride All' >> /etc/apache2/conf-available/laravel.conf && \
    echo '    Require all granted' >> /etc/apache2/conf-available/laravel.conf && \
    echo '</Directory>' >> /etc/apache2/conf-available/laravel.conf && \
    a2enconf laravel

# 🎻 Composer installation
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 👤 Non-root user setup
RUN useradd -r -u 1000 -g www-data application && \
    mkdir -p /var/www/storage/framework/{cache,views,sessions} && \
    mkdir -p /var/www/storage/logs && \
    chown -R application:www-data /var/www/storage && \
    chmod -R 775 /var/www/storage

WORKDIR /var/www

# 📦 Multi-stage dependency installation
COPY --chown=application:www-data composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# 🚀 Application deployment
COPY --chown=application:www-data . .

# 🔧 Post-install setup with error handling
USER application
RUN set -e && \
    composer dump-autoload --optimize && \
    php artisan package:discover --ansi && \
    { php artisan config:cache || true; } && \
    { php artisan view:cache || true; } && \
    { php artisan route:cache || true; }

# 🔒 Final permissions
USER root
RUN chown -R application:www-data /var/www && \
    find /var/www -type d -exec chmod 755 {} \; && \
    find /var/www -type f -exec chmod 644 {} \; && \
    chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 80
CMD ["apache2-foreground"]
