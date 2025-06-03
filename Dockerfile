FROM php:8.2-apache

# 🛠 Install system dependencies + PostgreSQL support
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
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

# ✅ COPY ONLY composer files first to install dependencies cleanly
COPY composer.json composer.lock ./

# 🧰 Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# 📦 Now copy the rest of the application
COPY . .

# 🔒 Set proper permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# 🌐 Expose HTTP port
EXPOSE 80

# ▶️ Start Apache
CMD ["apache2-foreground"]
