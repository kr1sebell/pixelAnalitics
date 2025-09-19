# PixelAnalytics

PixelAnalytics — минимальный аналитический стек для e-commerce с витриной показателей и простыми ETL-скриптами.

## Структура репозитория

```
pixel-analytics/
├── backend/            # PHP 5.6 API + ETL классы
├── frontend/           # React SPA на Vite
├── sql/                # DWH схема и сиды
├── cli/                # CLI-скрипты ETL
└── scripts/            # установка, миграции и cron
```

## Быстрый старт

1. Убедитесь, что установлены PHP ≥5.6, MySQL ≥5.6 и Node.js 16.x.
2. Скопируйте `.env.example` в `.env` и заполните доступы:
   * `PROD_DB_*` — доступ только на чтение к боевой БД заказов;
   * `AN_DB_*` — доступ на чтение/запись к аналитической БД;
   * `VK_*` — токен и параметры API ВКонтакте;
   * `TIMEZONE` — таймзона (по умолчанию Europe/Moscow).
3. Запустите установку: `bash scripts/install.sh`
   * Скрипт проверит версии, создаст БД `analytics`, применит `sql/*.sql` и соберёт фронтенд.
4. Для повторного применения схемы используйте `bash scripts/migrate.sh`.

## Запуск API

API — это `php -S 0.0.0.0:$HTTP_PORT -t backend/public`. По умолчанию порт 8080. Эндпоинты возвращают JSON и кэшируются файлом на 5–15 минут.

Доступные маршруты:

| Метод | Путь | Описание |
| ----- | ---- | -------- |
| GET | `/api/health` | Проверка состояния сервиса |
| GET | `/api/kpi?period=30d` | Оборот, заказы, средний чек, ретеншн |
| GET | `/api/segments/compare?periods=2` | Сравнение сегментов WoW |
| GET | `/api/rfm?filter=r>=4&f>=3&m>=3&limit=100` | Топ пользователей по RFM |
| GET | `/api/products/daily?from=YYYY-MM-DD&to=YYYY-MM-DD` | Дневной тренд продаж |
| GET | `/api/cohorts` | Когортная таблица (последние 12) |

## ETL-процессы

### cli/etl_extract.php

* Инкрементально копирует новые заказы из прод-БД в `stg_*`, затем апсертом обновляет `dim_user`, `dim_product`, `fact_orders`, `fact_order_items`.
* Водяной знак `orders_last_id` хранится в `etl_watermarks`.
* Телефоны хэшируются через SHA256 → BINARY(32).
* Логи записываются в `scripts/logs/etl_extract.log`.

### cli/etl_vk_update.php

* Ищет пользователей с `vk_id` без профиля или устаревшим `fetched_at` (старше `VK_FETCH_COOLDOWN_DAYS`).
* Обращается к VK API батчами по 100 с задержкой 0.35–0.5 с и повтором при `too many requests`.
* Обновляет таблицу `vk_profiles` и сохраняет сырой ответ в `raw_json`.

### cli/etl_build_summaries.php

* Пересчитывает `summary_rfm`, `summary_segments_daily` (за последние 7 дней), `summary_products_daily` (30 дней) и `summary_cohorts`.
* Скрипты возвращают код 0 при успехе и печатают краткую сводку.

Пример cron (`scripts/cron_examples.txt`):

```
0 3 * * *  /usr/bin/php /var/www/pixel-analytics/cli/etl_extract.php >> /var/log/pixel-analytics/etl_extract.log 2>&1
10 3 * * * /usr/bin/php /var/www/pixel-analytics/cli/etl_vk_update.php >> /var/log/pixel-analytics/etl_vk_update.log 2>&1
30 3 * * * /usr/bin/php /var/www/pixel-analytics/cli/etl_build_summaries.php >> /var/log/pixel-analytics/etl_build_summaries.log 2>&1
```

## Фронтенд

* React 18 + Vite.
* В виджетах используются `chart.js` + `react-chartjs-2`.
* Сборка: `cd frontend && npm install && npm run build`.
* Готовые файлы появляются в `frontend/dist` и могут быть отданы любым веб-сервером.

## Рабочий процесс аналитики

1. **Extract** — вытягивает инкрементальные заказы и пользователей, обновляет витрины.
2. **VK Enrich** — пополняет демографию через VK API (с уважением к флагу `do_not_profile`).
3. **Build Summaries** — собирает RFM, сегменты, продукты и когорты для BI-дашборда.
4. **API** — отдаёт данные SPA и внешним системам (по необходимости).
5. **SPA** — визуализирует KPI, сравнение сегментов, тепловую карту RFM, тренды и когорты.

## Подготовка тестовых данных

* После развёртывания схемы можно вставить тестовые строки в `stg_*` и `fact_*`, затем запустить `cli/etl_build_summaries.php`.
* Для VK достаточно заполнить `stg_users.vk_id` и запустить `cli/etl_vk_update.php` (понадобится валидный токен).

## Безопасность

* Флаг `dim_user.do_not_profile = 1` исключает пользователя из обогащения и аналитики.
* Телефон хранится только в виде `phone_sha256`.
* Токены VK задаются через `.env`, не коммитятся в репозиторий.

