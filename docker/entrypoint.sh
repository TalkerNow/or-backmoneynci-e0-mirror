#!/bin/sh
set -e

echo "🚀 Starting Laravel application..."

# Attendre que la base de données soit prête (si elle existe)
if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "localhost" ]; then
    echo "⏳ Waiting for database connection..."
    timeout=60
    while ! nc -z $DB_HOST ${DB_PORT:-3306} 2>/dev/null; do
        timeout=$((timeout - 1))
        if [ $timeout -le 0 ]; then
            echo "❌ Database connection timeout"
            break
        fi
        sleep 1
    done
    if [ $timeout -gt 0 ]; then
        echo "✅ Database is ready"
    fi
fi

# Garantir les bonnes permissions au démarrage
echo "🔧 Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Installer les dépendances Composer si nécessaire
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
elif [ "composer.lock" -nt "vendor/autoload.php" ]; then
    echo "📦 Updating Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
else
    echo "✅ Composer dependencies are up to date"
fi

# Créer les dossiers de storage si nécessaire
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Mettre en cache la configuration si APP_ENV=production
if [ "$APP_ENV" = "production" ]; then
    echo "⚡ Caching configuration for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

echo "✅ Application ready!"

# Lancer PHP-FPM
exec php-fpm
