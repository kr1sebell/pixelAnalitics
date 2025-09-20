#!/bin/bash
set -e

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
cd "$ROOT_DIR"

check_version() {
  local cmd="$1"
  local pattern="$2"
  if ! command -v "$cmd" >/dev/null 2>&1; then
    echo "[ERROR] $cmd не найден"
    exit 1
  fi
  if ! $cmd --version 2>&1 | grep -q "$pattern"; then
    echo "[ERROR] Требуется $cmd версии $pattern"
    exit 1
  fi
}

check_version php "PHP 5.6"
check_version mysql "Distrib 5.6"
check_version node "v16"

ENV_FILE="$ROOT_DIR/.env"
if [ ! -f "$ENV_FILE" ]; then
  cp "$ROOT_DIR/.env.example" "$ENV_FILE"
  echo "Создан $ENV_FILE. Заполните параметры и запустите скрипт снова."
  exit 0
fi

set -a
source "$ENV_FILE"
set +a

if [ -z "$AN_DB_HOST" ] || [ -z "$AN_DB_USER" ] || [ -z "$AN_DB_NAME" ]; then
  echo "[ERROR] Проверьте настройки аналитической БД в .env"
  exit 1
fi

export MYSQL_PWD="$AN_DB_PASS"
echo "Создаю базу данных $AN_DB_NAME..."
mysql -h "$AN_DB_HOST" -u "$AN_DB_USER" -e "CREATE DATABASE IF NOT EXISTS \`$AN_DB_NAME\` CHARACTER SET utf8 COLLATE utf8_general_ci;"

for file in sql/01_analytics_schema.sql sql/02_seed_dim_date.sql sql/03_indexes.sql; do
  echo "Применяю $file..."
  mysql -h "$AN_DB_HOST" -u "$AN_DB_USER" "$AN_DB_NAME" < "$file"
done

unset MYSQL_PWD

echo "Устанавливаю фронтенд..."
cd "$ROOT_DIR/frontend"
npm install
npm run build

cd "$ROOT_DIR"
echo "Готово. Запуск API: php -S 0.0.0.0:${HTTP_PORT:-8080} -t backend/public"
echo "ETL: php backend/cli/etl_extract.php; php backend/cli/etl_build_summaries.php"
echo "Примеры cron см. в scripts/cron_examples.txt"
