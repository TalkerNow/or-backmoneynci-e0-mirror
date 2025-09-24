#!/usr/bin/env bash
set -euo pipefail

# Raccourci pour exécuter des commandes dans le conteneur PHP
C='docker compose exec -T php bash -lc'

echo "🔄 Git pull…"
git pull --ff-only

echo "🐳 Sanity check conteneur PHP…"
$C 'php -v'

echo "🛑 Maintenance ON…"
$C 'php artisan down || true'

echo "📦 Dépendances Composer (si besoin)…"
$C 'composer install --no-dev --prefer-dist --no-interaction'

echo "🧹 Clear caches avant build…"
$C 'php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear'

echo "🗃️  Migrations…"
$C 'php artisan migrate --force'

echo "🧼 Nettoyage tokens reset (optionnel)…"
$C 'php artisan auth:clear-resets || true'

echo "⚡ Rebuild caches…"
# (tu peux aussi faire 'php artisan optimize', mais je préfère expliciter)
$C 'php artisan config:cache && php artisan route:cache && php artisan view:cache'

echo "✅ Maintenance OFF…"
$C 'php artisan up'

echo "📄 Petit tail des logs pour vérifier (100 lignes)…"
$C 'tail -n 100 storage/logs/laravel.log || true'

echo "🎉 Déploiement terminé."

