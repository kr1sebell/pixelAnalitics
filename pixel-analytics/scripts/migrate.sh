#!/bin/bash
set -e

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
cd "$ROOT_DIR"

ENV_FILE="$ROOT_DIR/.env"
if [ ! -f "$ENV_FILE" ]; then
  echo "[ERROR] Нет .env. Скопируйте .env.example и заполните."
  exit 1
fi

set -a
source "$ENV_FILE"
set +a

if [ -z "$AN_DB_HOST" ] || [ -z "$AN_DB_USER" ] || [ -z "$AN_DB_NAME" ]; then
  echo "[ERROR] Проверьте параметры аналитической БД"
  exit 1
fi

export MYSQL_PWD="$AN_DB_PASS"
mysql -h "$AN_DB_HOST" -u "$AN_DB_USER" -e "CREATE DATABASE IF NOT EXISTS \`$AN_DB_NAME\` CHARACTER SET utf8 COLLATE utf8_general_ci;"

for file in sql/01_analytics_schema.sql sql/02_seed_dim_date.sql sql/03_indexes.sql; do
  echo "Применяю $file..."
  mysql -h "$AN_DB_HOST" -u "$AN_DB_USER" "$AN_DB_NAME" < "$file"
done

unset MYSQL_PWD

echo "Миграции выполнены"
