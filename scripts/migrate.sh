#!/usr/bin/env bash
set -euo pipefail

if [ ! -f .env ]; then
  echo "Файл .env не найден. Запустите install.sh" >&2
  exit 1
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

for file in sql/*.sql; do
  echo "Применяем ${file}"
  "${MYSQL_CMD[@]}" < "${file}"
done
