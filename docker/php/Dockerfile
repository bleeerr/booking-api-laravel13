
FROM php:8.4-fpm-bookworm

ARG LOCAL_UID=1000
ARG LOCAL_GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        intl \
        zip \
        bcmath \
        pcntl \
        mbstring \
        opcache \
    && pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug \
    && rm -rf /var/lib/apt/lists/*

RUN groupmod -o -g "${LOCAL_GID}" www-data \
    && usermod -o -u "${LOCAL_UID}" -g www-data www-data

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

WORKDIR /var/www/html

CMD ["php-fpm"]
