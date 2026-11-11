FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libpq-dev \
    && docker-php-ext-install intl pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
