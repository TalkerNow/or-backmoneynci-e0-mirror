#!/usr/bin/env bash
set -e

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env.local"

# Récupère une valeur dans .env.local
get_env() {
  local key="$1"
  local default="$2"
  local value

  if [ -f "$ENV_FILE" ]; then
    value=$(grep -E "^${key}=" "$ENV_FILE" | tail -n 1 | cut -d '=' -f2- || true)
  fi

  if [ -z "$value" ]; then
    echo "$default"
  else
    echo "$value"
  fi
}

# ===== PROD (lecture seule) =====
PROD_HOST=$(get_env "PROD_DB_HOST" "prod-db-host")
PROD_PORT=$(get_env "PROD_DB_PORT" "3306")
PROD_DB=$(get_env "PROD_DB_DATABASE" "moneynci_prod")
PROD_USER=$(get_env "PROD_DB_USERNAME" "prod_user")
PROD_PASS=$(get_env "PROD_DB_PASSWORD" "prod_password")

# ===== LOCAL (ta DB Docker) =====
LOCAL_HOST=$(get_env "DB_HOST" "db")           # host vu depuis le container db
LOCAL_PORT=$(get_env "DB_PORT" "3306")
LOCAL_DB=$(get_env "DB_DATABASE" "moneynci_local")
LOCAL_USER=$(get_env "DB_USERNAME" "debian")
LOCAL_PASS=$(get_env "DB_PASSWORD" "password")

echo "=== Sync DB PROD ($PROD_DB@$PROD_HOST:$PROD_PORT) -> LOCAL ($LOCAL_DB@$LOCAL_HOST:$LOCAL_PORT) ==="

# Commande à exécuter DANS le container db
docker compose exec -T db sh -c "
  echo 'Dump de la base PROD...';
  mysqldump -h \"$PROD_HOST\" -P \"$PROD_PORT\" -u \"$PROD_USER\" -p\"$PROD_PASS\" \
    --single-transaction --quick --routines --triggers \
    \"$PROD_DB\" > /tmp/moneynci_prod_dump.sql;

  echo 'Restauration dans la base locale Docker...';
  mysql -h \"$LOCAL_HOST\" -P \"$LOCAL_PORT\" -u \"$LOCAL_USER\" -p\"$LOCAL_PASS\" \
    -e \"DROP DATABASE IF EXISTS \\\`$LOCAL_DB\\\`; CREATE DATABASE \\\`$LOCAL_DB\\\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\";

  mysql -h \"$LOCAL_HOST\" -P \"$LOCAL_PORT\" -u \"$LOCAL_USER\" -p\"$LOCAL_PASS\" \
    \"$LOCAL_DB\" < /tmp/moneynci_prod_dump.sql;

  echo 'Sync terminé dans le container db.';
"

echo "Terminé."