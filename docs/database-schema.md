# Coworking Booking API — схема БД v1

```mermaid
erDiagram
    USERS ||--o{ BOOKINGS : creates
    WORKSPACES ||--o{ ROOMS : contains
    ROOMS ||--o{ BOOKINGS : has

    USERS {
        bigint id PK
        varchar name "max 255"
        varchar email UK "max 255"
        varchar password "max 255"
        varchar role "max 20; user, admin"
    }

    WORKSPACES {
        bigint id PK
        varchar name "max 150"
        varchar timezone "max 64"
        varchar address "max 500"
    }

    ROOMS {
        bigint id PK
        bigint workspace_id FK
        varchar name "max 150"
        text description "nullable"
        int capacity
        bigint price_per_hour_minor
        char currency_code "3 chars; ISO 4217"
        boolean is_active
    }

    BOOKINGS {
        bigint id PK
        bigint user_id FK
        bigint room_id FK
        varchar status "max 20; pending, confirmed, cancelled"
        timestamptz starts_at
        timestamptz ends_at
        bigint total_price_minor
        char currency_code "3 chars; ISO 4217"
        timestamptz cancelled_at "nullable"
    }
```

## Связи

- `WORKSPACES 1:N ROOMS`
- `ROOMS 1:N BOOKINGS`
- `USERS 1:N BOOKINGS`

## Ограничения

- `users.role`: строковый PHP Enum `user | admin`.
- `bookings.status`: строковый PHP Enum `pending | confirmed | cancelled`.
- `price_per_hour_minor` и `total_price_minor`: `BIGINT`, сумма в минимальных единицах валюты; значение неотрицательное (`CHECK >= 0`).
- `capacity > 0`; `ends_at > starts_at`.
- Для активных бронирований одной комнаты запрещены пересекающиеся интервалы (PostgreSQL exclusion constraint; реализуем в миграции).
