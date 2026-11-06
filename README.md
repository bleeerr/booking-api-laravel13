# Coworking Booking API

RESTful API для управления коворкингами, переговорными комнатами и их бронированием. Демонстрационный backend-проект на PHP 8.4 и Laravel 13 с акцентом на проектирование API, бизнес-правила, целостность данных, тестирование и контейнеризацию.

## Возможности

**Пользователь (`user`):**
- регистрация, вход, выход и просмотр профиля;
- просмотр коворкингов и комнат;
- проверка доступности комнаты на выбранный интервал;
- создание, просмотр и отмена собственных бронирований.

**Администратор (`admin`):**
- все возможности обычного пользователя;
- создание, обновление и удаление коворкингов и комнат;
- подтверждение бронирований.

**Ключевые бизнес-правила (по API-контракту):**
- новая бронь получает статус `pending`; администратор может подтвердить её (`confirmed`), допустимые бронирования можно отменять (`cancelled`);
- брони в статусах `pending` и `confirmed` не должны пересекаться по времени в одной комнате; защита запланирована на уровне PostgreSQL;
- стоимость рассчитывается сервером, денежные значения хранятся в минимальных единицах валюты;
- неактивные комнаты недоступны для бронирования.

## Технологический стек

| Компонент | Технологии |
| --- | --- |
| Backend | PHP 8.4 (PHP-FPM), Laravel 13 |
| База данных | PostgreSQL 17 |
| Redis | Redis 7 (сервис в Docker Compose) |
| Веб-сервер | Nginx |
| Аутентификация | Laravel Sanctum |
| Контейнеризация | Docker, Docker Compose |
| Тестирование | PHPUnit 12 |
| Статический анализ | PHPStan + Larastan |
| Стиль кода | Laravel Pint |
| API-контракт | OpenAPI 3.1 / Swagger Editor |
| CI | GitHub Actions (анализ, стиль, тесты) |
| Отладка | Xdebug (в образе разработки) |

## Документация

- [OpenAPI-спецификация](./docs/openapi.yaml) — маршруты, форматы запросов и ответов, роли и ошибки.
- [Схема БД и ER-диаграмма](./docs/database-schema.md) — таблицы, связи и ограничения.
- [Swagger Editor](https://editor.swagger.io/) — просмотр спецификации: **File → Import File** и выбрать `docs/openapi.yaml`.

Контракт предусматривает префикс `/api/v1`. Отдельный встроенный Swagger UI в проверенной конфигурации репозитория не обнаружен.

## Локальный запуск

### Требования

- Git;
- Docker Engine + Docker Compose plugin либо Docker Desktop;
- для Windows — Docker Desktop с WSL 2 (рекомендуется запускать команды из WSL).

### 1. Получить проект

```bash
git clone https://github.com/bleeerr/booking-api-laravel13.git
cd booking-api-laravel13
```

### 2. Настроить окружение

```bash
cp .env.example .env
```

В `.env` задать следующие значения (пароль выберите самостоятельно):

```dotenv
APP_URL=http://localhost:8080

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=booking_api
DB_USERNAME=booking_api
DB_PASSWORD=change_this_password

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
```

**Важно:** в текущем `.env.example` по умолчанию указан SQLite (`DB_CONNECTION=sqlite`). Для Docker Compose с PostgreSQL необходимо заменить параметры, как показано выше. Значения `DB_DATABASE`, `DB_USERNAME` и `DB_PASSWORD` передаются контейнеру PostgreSQL из `.env`.

### 3. Собрать и запустить контейнеры

Из корня репозитория (Compose-файл — `compose.yaml`):

```bash
docker compose up -d --build
docker compose ps
```

Сервисы: `app` (PHP-FPM), `nginx`, `postgres`, `redis`. Образ `app` собирается из `docker/php/Dockerfile`.

### 4. Установить зависимости и подготовить приложение

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Если `composer install` сообщает об ошибках прав доступа к файлам, проверьте права каталога проекта и параметры `LOCAL_UID` / `LOCAL_GID` в `compose.yaml`.

### 5. Проверить запуск

- HTTP: **http://localhost:8080**
- Маршруты API: **http://localhost:8080/api/v1**
- Список зарегистрированных маршрутов:

```bash
docker compose exec app php artisan route:list --path=api
```

Nginx опубликован только на `127.0.0.1:8080`; PostgreSQL — на `127.0.0.1:5433` (из контейнера приложения: `postgres:5432`). Redis не опубликован на хосте.

**Остановить контейнеры:**

```bash
docker compose down
```

Данные PostgreSQL и Redis сохраняются в именованных Docker volumes. `docker compose down -v` удалит и их.

## Демонстрационные данные (Seeders)

Для локальной разработки используются стандартные механизмы Laravel: **Factories** для генерации тестовых моделей и **Seeders** для наполнения базы данных.

### Структура сидеров

`DatabaseSeeder` запускает сидеры в порядке зависимостей:

1. `UserSeeder` — пользователи и администратор.
2. `WorkspaceSeeder` — коворкинги.
3. `RoomSeeder` — комнаты существующих коворкингов.
4. `BookingSeeder` — бронирования комнат.

После полного пересоздания БД формируется следующий набор данных:

| Таблица | Количество | Описание |
|---|---:|---|
| `users` | 11 | 1 администратор, 10 пользователей |
| `workspaces` | 5 | Демонстрационные коворкинги |
| `rooms` | 25 | По 5 комнат на коворкинг |
| `bookings` | 75 | По 25 бронирований каждого статуса |

Для бронирований соблюдаются бизнес-правила: `pending` и `confirmed` не пересекаются в пределах одной комнаты, а `cancelled` не блокирует интервалы.

### Сброс и заполнение локальной БД

Для полного пересоздания структуры и повторного наполнения:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

**Внимание:** команда удаляет все таблицы и данные в подключённой БД. Использовать исключительно в изолированном локальном или тестовом окружении.

Для запуска сидеров без пересоздания БД:

```bash
docker compose exec app php artisan db:seed
```

Сидеры не являются идемпотентными. Повторный запуск `db:seed` может создать дополнительные записи или вызвать конфликт уникальности.

Данные Faker генерируются заново при каждом запуске. Гарантируются структура и количество записей, но не идентичность случайных значений.

### Защита production

`DatabaseSeeder` содержит проверку окружения, разрешающую демонстрационное наполнение только при `APP_ENV=local` или `APP_ENV=testing`.

Эта проверка не защищает от прямого запуска отдельных сидеров и не предотвращает удаление таблиц через `migrate:fresh`.

Для production необходимо:

- Не использовать `migrate:fresh` и демо-сидеры.
- Применять миграции через `php artisan migrate --force`.
- Использовать отдельную PostgreSQL-базу и ограниченные права доступа.
- Выполнять резервное копирование перед изменениями структуры БД.
- Исключить разрушительные команды из production CI/CD.

Демонстрационные учётные записи и пароли предназначены только для локального использования.

## Развёртывание на Linux-сервере

Существующий `compose.yaml` ориентирован на разработку: код подключён bind mount, Xdebug включён в PHP-образ, а Nginx слушает только `127.0.0.1:8080`. Это **не готовая production-конфигурация**. Для публичного сервера нужны отдельные настройки безопасности и развёртывания.

1. Установите Docker Engine, Docker Compose и Git; клонируйте репозиторий.
2. Создайте серверный `.env` и настройте PostgreSQL/Redis (аналогично локальному запуску), а также `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain.example`. Используйте сильные секреты, не коммитьте `.env`.
3. Настройте HTTPS и обратный прокси, направляющий запросы на `127.0.0.1:8080`. Не открывайте PostgreSQL/Redis в публичную сеть.
4. Соберите и запустите контейнеры:

   ```bash
   docker compose up -d --build
   ```

5. Для первого развёртывания установите зависимости и создайте постоянный ключ приложения:

   ```bash
   docker compose exec app composer install --no-dev --optimize-autoloader
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --force
   docker compose exec app php artisan config:cache
   ```

6. Проверьте логи, доступность API и TLS. Настройте резервные копии PostgreSQL.

При **последующих** развёртываниях не запускайте `key:generate` повторно: сохраняйте исходный `APP_KEY`. Перед запуском миграций делайте резервную копию БД.

Для полноценного production-развёртывания рекомендуется отдельный Compose override/образ без Xdebug и dev-bind-mount, управление секретами и настройка мониторинга. Такие файлы в проверенной конфигурации не подтверждены.

## Тесты и проверка качества

В `composer.json` предусмотрены команды:

```bash
docker compose exec app composer test
docker compose exec app composer analyse
docker compose exec app composer lint
docker compose exec app composer check
```

`composer check` последовательно запускает статический анализ, проверку стиля и тесты. Конфигурация PHPStan/Larastan — `phpstan.neon.dist` (уровень 6). PHPUnit в `phpunit.xml` использует SQLite in-memory для тестов.

В `.github/workflows/ci.yml` GitHub Actions запускает эти проверки на `push` и `pull_request` в `main` с PHP 8.4.

## Структура основных файлов

```text
booking-api-laravel13/
├── app/                       # Код Laravel-приложения
├── database/                  # Миграции, фабрики, сидеры
├── docker/
│   ├── nginx/default.conf     # Конфигурация Nginx
│   └── php/Dockerfile         # Образ PHP 8.4-FPM
├── docs/
│   ├── database-schema.md     # ER-диаграмма и схема БД
│   └── openapi.yaml           # Контракт REST API
├── routes/api.php             # API-маршруты
├── .github/workflows/ci.yml   # CI
├── compose.yaml               # Docker Compose
└── composer.json              # Зависимости и команды проверки
```

## Статус реализации

API-контракт описывает запланированную систему в целом. По текущему `routes/api.php` подтверждены маршруты `/api/v1/register`, `/api/v1/login`, `/api/v1/me`, `/api/v1/logout`. Остальные маршруты из OpenAPI нельзя считать реализованными только на основании спецификации.

---

[Репозиторий на GitHub](https://github.com/bleeerr/booking-api-laravel13)
