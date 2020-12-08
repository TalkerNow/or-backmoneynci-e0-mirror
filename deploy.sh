#!/bin/bash

# Turn on maintenance mode
php artisan down || true

# Pull the latest changes from the git repository
# git reset --hard
# git clean -df
git pull origin master

# Install/update composer dependecies
sudo composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Run database migrations
sudo php artisan migrate --force

# Clear caches
sudo php artisan cache:clear

# Clear expired password reset tokens
sudo php artisan auth:clear-resets

# Clear and cache routes
sudo php artisan route:cache

# Clear and cache config
sudo php artisan config:cache

# Clear and cache views
sudo php artisan view:cache

# Turn off maintenance mode
php artisan up
