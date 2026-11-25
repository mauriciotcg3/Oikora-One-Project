# Ambiente de desenvolvimento da API (OIK-ADR-0011 §2, §4). O código entra por volume, sem COPY.
ARG PHP_VERSAO
FROM php:${PHP_VERSAO}-fpm-alpine

RUN apk add --no-cache git unzip libpq icu-libs libzip \
    && apk add --no-cache --virtual .dependencias-build $PHPIZE_DEPS postgresql-dev icu-dev libzip-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl pcntl zip \
    && apk del .dependencias-build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api
EXPOSE 9000
CMD ["php-fpm"]
