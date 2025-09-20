# PixelAnalytics

PixelAnalytics — витрина аналитики заказов на PHP 5.6 и React. Проект содержит ETL-пайплайн, REST API и фронтенд-визуализацию для предрассчитанных сводок.

## Стек

- PHP 5.6 без фреймворков, SafeMySQL для БД
- MySQL 5.6 (схема `analytics`)
- React (Node.js 16, Vite)
- Chart.js для визуализаций

## Структура
```
pixel-analytics/
  backend/
  frontend/
  sql/
  scripts/
```

## Быстрый старт

```bash
./scripts/install.sh
```

Скрипт проверит версии, создаст БД `analytics`, применит миграции, соберёт фронтенд и подготовит `.env`.

### Настройка окружения

1. Создайте `.env` на основе `.env.example` и заполните доступы к продовой и аналитической базе данных, VK API (опционально).
2. В MySQL создайте пользователя с доступом к схеме `analytics`.

### Миграции

Для повторного применения схемы выполните:
```bash
./scripts/migrate.sh
```

### ETL

- `php backend/cli/etl_extract.php` — перенос новых заказов и справочников.
- `php backend/cli/etl_vk_update.php` — обновление профилей VK.
- `php backend/cli/etl_build_summaries.php` — пересчёт витрин (RFM, сегменты).

### HTTP API

Запустите development сервер PHP:
```bash
php -S 0.0.0.0:$HTTP_PORT -t backend/public
```

Подробная документация доступна по `/`.

### Фронтенд

```bash
cd frontend
npm install
npm run dev
```

Продакшен-билд лежит в `frontend/dist` и может обслуживаться любым статическим сервером. Готовая сборка использует API по относительным путям (`/api/...`).

### Крон

Примеры заданий см. в `scripts/cron_examples.txt`.

## Тестирование

- `scripts/migrate.sh` должно накатывать схему без ошибок.
- Первый запуск `etl_extract` наполняет `fact_orders`, `fact_order_items`, `dim_user`, `dim_product` и выставляет `first_order_dt`/`last_order_dt`.
- `etl_build_summaries` формирует `summary_rfm` и `summary_segments_daily`.
- Эндпоинты API возвращают данные и используют кэш.
- Фронтенд показывает четыре виджета с графиками.

## Лицензия

MIT
