# Coworking Booking API

REST API для бронирования переговорных комнат: управление коворкингами и комнатами, проверка доступности, создание и подтверждение бронирований, email-уведомления через Redis Queue.

**Стек:** PHP 8.4 · Laravel 13 · PostgreSQL 17 · Redis 7 · Sanctum · Docker · PHPUnit 12 · OpenAPI 3.1

## Quick Start

**Нужны:** Docker с Compose, Git и Bash (Linux / macOS / WSL). Свободный порт `8080`.

```bash
git clone https://github.com/bleeerr/booking-api-laravel13.git
cd booking-api-laravel13
bash bin/setup-demo.sh
```

Скрипт создаст `.env` (если его ещё нет), запустит контейнеры, установит PHP-зависимости, настроит ключ, выполнит миграции и при пустой БД создаст демо-данные.

**API:** http://localhost:8080/api/v1

**Демо-аккаунты** (пароли выводятся в терминале только при первоначальном заполнении):

| Роль | Email |
| --- | --- |
| Admin | `admin@example.com` |
| User | `user@example.com` |

> При повторном запуске скрипт не удаляет данные и не создаёт пользователей заново. Пароли ранее созданных аккаунтов не выводятся повторно.

## Попробовать API

```bash
curl http://localhost:8080/api/v1/workspaces
```

Для защищённых маршрутов импортируйте [`docs/postman/Coworking-Booking-API.postman_collection.json`](docs/postman/Coworking-Booking-API.postman_collection.json) в Postman, введите напечатанные демо-пароли в переменные коллекции `admin_password` и `user_password`, затем запускайте запросы в порядке: **Login Admin → List Workspaces → List Rooms → Login User → Create Booking → Confirm Booking**. Токены и ID автоматически сохраняются в переменных коллекции.

**Проверка кода:**

```bash
docker compose exec app composer check
```

**Остановка:** `docker compose down` (без удаления Docker volumes).

## Документация

- [API contract (OpenAPI)](docs/openapi.yaml)
- [Схема БД](docs/database-schema.md)
- [Архитектура и бизнес-правила](docs/architecture.md)
- [Локальная разработка и тесты](docs/development.md)
- [Развёртывание и ограничения](docs/deployment.md)

> Docker Compose в этом репозитории предназначен для локальной разработки, а не для публичного production-развёртывания.
