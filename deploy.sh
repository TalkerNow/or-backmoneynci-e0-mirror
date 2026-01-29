#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./deploy.sh        # déploiement sur PROD (php-prod / DB moneynci)
#   ./deploy.sh test   # déploiement sur TEST (php-test / DB moneynci_test)

ENVIRONMENT="${1:-prod}"

case "$ENVIRONMENT" in
  prod)
    SERVICE="php-prod"
    ;;
  test)
    SERVICE="php-test"
    ;;
  *)
    echo "❌ Environnement inconnu: $ENVIRONMENT"
    echo "   Utilise: ./deploy.sh [prod|test]"
    exit 1
    ;;
esac

# Raccourci pour exécuter des commandes dans le conteneur PHP ciblé
C="docker compose exec -T $SERVICE bash -lc"

echo "🚀 Déploiement sur l'environnement: $ENVIRONMENT (service: $SERVICE)"

echo "�� Git pull…"
git pull --ff-only

echo "🐳 Sanity check conteneur PHP…"
$C 'php -v'

echo "🛑 Maintenance ON…"
$C 'php artisan down || true'

echo "📦 Dépendances Composer (si besoin)…"
$C 'composer install --no-dev --prefer-dist --no-interaction'

echo "🧹 Clear caches avant build…"
# on NE reconstruit PAS le cache config, on le vide juste
$C 'php artisan optimize:clear || true'

echo "🗃️  Migrations…"
$C 'php artisan migrate --force'

echo "🧼 Nettoyage tokens reset (optionnel)…"
$C 'php artisan auth:clear-resets || true'

echo "✅ Maintenance OFF…"
$C 'php artisan up'

echo "📄 Petit tail des logs pour vérifier (100 lignes)…"
$C 'tail -n 100 storage/logs/laravel.log || true'

echo "🎉 Déploiement terminé sur $ENVIRONMENT."

