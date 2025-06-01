#!/bin/bash
set -o errexit

# Install dependencies
composer install --no-dev --optimize-autoloader

# Generate key (if missing)
php artisan key:generate --force

# Migrate database
php artisan migrate --force

# Optional: Link storage if using uploads
php artisan storage:link

php artisan queue:work

# Cache optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
