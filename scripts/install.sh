#!/usr/bin/env bash
set -euo pipefail

if ! command -v php >/dev/null 2>&1; then
  echo "PHP не найден. Установите PHP 5.6+" >&2
  exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "PHP version: ${PHP_VERSION}"

if ! command -v mysql >/dev/null 2>&1; then
  echo "mysql клиент не найден" >&2
  exit 1
fi

echo "MySQL client version: $(mysql --version)"

if ! command -v node >/dev/null 2>&1; then
  echo "Node.js 16.x обязателен" >&2
  exit 1
fi

echo "Node.js version: $(node --version)"

echo "
== Подготовка окружения =="
if [ ! -f .env ]; then
  cp .env.example .env
  echo "Файл .env создан из примера. Проверьте и обновите доступы."
fi

set -a
source .env
set +a

if [ -z "${AN_DB_NAME:-}" ]; then
  echo "Переменные подключения к аналитической БД не заданы" >&2
  exit 1
fi

MYSQL_CMD=(mysql -h"${AN_DB_HOST}" -u"${AN_DB_USER}" "${AN_DB_NAME}" --default-character-set="${AN_DB_CHARSET:-utf8}")
if [ -n "${AN_DB_PASS:-}" ]; then
  MYSQL_CMD=(mysql -h"${AN_DB_HOST}" -u"${AN_DB_USER}" -p"${AN_DB_PASS}" "${AN_DB_NAME}" --default-character-set="${AN_DB_CHARSET:-utf8}")
fi

echo "Создаем базу данных ${AN_DB_NAME}"
mysql -h"${AN_DB_HOST}" -u"${AN_DB_USER}" ${AN_DB_PASS:+-p"${AN_DB_PASS}"} -e "CREATE DATABASE IF NOT EXISTS \`${AN_DB_NAME}\` CHARACTER SET utf8 COLLATE utf8_general_ci;"

echo "Применяем sql/*.sql"
for file in sql/*.sql; do
  echo " > ${file}"
  "${MYSQL_CMD[@]}" < "${file}"
done

echo "Сборка фронтенда"
(cd frontend && npm install && npm run build)

echo "Готово."
echo "Для запуска API: php -S 0.0.0.0:${HTTP_PORT:-8080} -t backend/public"
echo "Настройте cron по образцу из scripts/cron_examples.txt"
