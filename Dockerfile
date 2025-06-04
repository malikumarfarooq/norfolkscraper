FROM php:8.2-apache

# 🛠 Install system dependencies + PostgreSQL support
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonid-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd pdo pgsql pdo_pgsql

# 🔁 Enable Apache rewrite module
RUN a2enmod rewrite

# 🔇 Suppress Apache FQDN warnings
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# 📂 Set Apache document root to Laravel's public folder
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/public|' /etc/apache2/sites-available/000-default.conf

# 🔐 Allow .htaccess overrides for Laravel routing
RUN echo '<Directory /var/www/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/laravel.conf && \
    a2enconf laravel

# ⬇️ Copy Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 📍 Set working directory
WORKDIR /var/www

# 👤 Create non-root user for Composer (avoid root warnings)
RUN useradd -r -u 1000 -g www-data application
USER application

# 1️⃣ First copy only composer files
COPY --chown=application:www-data composer.json composer.lock ./

# 2️⃣ Install dependencies (without scripts)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# 3️⃣ Copy the rest of the application (excluding ignored files)
COPY --chown=application:www-data . .

# 4️⃣ Now run the post-install scripts manually
RUN php artisan package:discover --ansi

# 🔒 Set proper permissions (temporarily switch to root)
USER root
RUN chown -R application:www-data /var/www \
    && chmod -R 755 /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Create necessary Laravel directories if they don't exist
RUN mkdir -p /var/www/storage/framework/{cache,views,sessions} \
    && mkdir -p /var/www/storage/logs \
    && chown -R application:www-data /var/www/storage \
    && chmod -R 775 /var/www/storage

# 🌐 Expose HTTP port
EXPOSE 80

# ▶️ Start Apache (must run as root)
CMD ["apache2-foreground"]
