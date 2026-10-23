# Test and quality toolchain for payum-payu. Not a runtime image.
FROM php:8.4-cli-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache git unzip $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS linux-headers \
    && addgroup -g "${GID}" app \
    && adduser -D -u "${UID}" -G app app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN printf 'memory_limit=-1\n' > /usr/local/etc/php/conf.d/zz-memory.ini

ENV XDEBUG_MODE=coverage \
    COMPOSER_CACHE_DIR=/tmp/composer-cache

USER app
WORKDIR /app
