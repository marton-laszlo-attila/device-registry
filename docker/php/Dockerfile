FROM php:8.5-fpm-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache icu-dev libzip-dev git unzip $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql bcmath intl zip pcntl \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2.11 /usr/bin/composer /usr/bin/composer

COPY conf.d/security.ini "$PHP_INI_DIR/conf.d/zz-security.ini"

RUN addgroup -g ${GID} app && adduser -D -u ${UID} -G app app \
    && mkdir -p /var/www/html && chown app:app /var/www/html

WORKDIR /var/www/html
USER app
