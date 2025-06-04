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

# 🎛️ Apache configuration
RUN a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/public|' /etc/apache2/sites-available/000-default.conf \
    && echo '<Directory /var/www/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

# 🎻 Composer installation
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 👤 Non-root user setup
RUN useradd -r -u 1000 -g www-data application \
    && mkdir -p /var/www/storage/framework/{cache,views,sessions} \
    && mkdir -p /var/www/storage/logs \
    && chown -R application:www-data /var/www/storage \
    && chmod -R 775 /var/www/storage

WORKDIR /var/www

# 📦 Multi-stage dependency installation
COPY --chown=application:www-data composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# 🚀 Application deployment
COPY --chown=application:www-data . .

# 🔧 Post-install setup
USER application
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && php artisan optimize:clear

# 🔒 Final permissions
USER root
RUN chown -R application:www-data /var/www \
    && chmod -R 755 /var/www \
    && chmod -R 775 /var/www/bootstrap/cache

# 🚨 Health check
HEALTHCHECK --interval=30s --timeout=3s \
    CMD curl -f http://localhost/ || exit 1

EXPOSE 80
CMD ["apache2-foreground"]
