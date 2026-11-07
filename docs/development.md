# Local development

## Требования

Docker + Compose, Git, Bash (для `bin/setup-demo.sh`). Под Windows запускать из WSL, если репозиторий находится в Linux-файловой системе.

## Подготовка

```bash
bash bin/setup-demo.sh
```

Скрипт использует `.env.example`: PostgreSQL `postgres:5432`, Redis `redis:6379`, SMTP по умолчанию выключен (`MAIL_MAILER=log`), письма попадают в `storage/logs/mail.log`.

Перед запуском настройте свободные порты: `127.0.0.1:8080` (HTTP), `127.0.0.1:5433` (PostgreSQL). Для пользовательского `.env` обязательно обеспечьте согласованность настроек с `compose.yaml`.

Команды:

```bash
docker compose ps
docker compose exec app php artisan route:list --path=api
docker compose exec app composer check
docker compose exec app php artisan test
docker compose logs --tail=50 queue
docker compose exec app tail -n 50 storage/logs/mail.log
```

`composer check` последовательно запускает PHPStan/Larastan, Laravel Pint и PHPUnit. Тесты настроены на отдельную БД `booking_api_test` и используют `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`.

## Демо-данные

На **первой установке в пустую БД** сидеры создают пользователей, коворкинги, комнаты и бронирования. Пароли `admin@example.com` и `user@example.com` генерируются случайно и печатаются при выполнении `db:seed`.

Повторный `bash bin/setup-demo.sh` применяет миграции, но **не сидирует заново**, если в таблице `users` уже есть записи. Если пароли потеряны, их нельзя восстановить из БД; для локального окружения можно вручную задать новые или пересоздать демонстрационную БД.

**Опасная операция для одноразового демо (удаляет все таблицы и данные):**

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Не запускайте её на БД с ценными данными. Никогда не используйте её как автоматический шаг установки.

## Отладка

PHP-FPM в dev-образе содержит Xdebug. Для SMTP вместо логирования задайте `MAIL_MAILER=smtp` и параметры SMTP в `.env`, затем `docker compose exec app php artisan config:clear` и `docker compose restart queue`.

## Документы

- [`openapi.yaml`](openapi.yaml) — контракт HTTP API.
- [`database-schema.md`](database-schema.md) — ER-схема.
- [`architecture.md`](architecture.md) — бизнес-правила и архитектура.
