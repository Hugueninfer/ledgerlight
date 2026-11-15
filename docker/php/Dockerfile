# syntax=docker/dockerfile:1

# ---- base: FrankenPHP + extensions + composer ----
FROM dunglas/frankenphp:php8.5-alpine AS base

RUN install-php-extensions pdo_mysql redis pcntl intl zip bcmath opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Plain HTTP on a non-privileged port; TLS is handled by the edge (Caddy) in front.
ENV SERVER_NAME=:8000 \
    XDG_CONFIG_HOME=/config \
    XDG_DATA_HOME=/data \
    COMPOSER_HOME=/tmp/composer

WORKDIR /app

# ---- dev: runs as a non-root user matching the host uid/gid so bind mounts stay owned by the host user ----
FROM base AS dev

ARG UID=1000
ARG GID=1000

RUN addgroup -g "${GID}" app \
    && adduser -D -u "${UID}" -G app -h /home/app app \
    && mkdir -p /config /data /tmp/composer \
    && chown -R app:app /config /data /tmp/composer /app

# Development ini: no opcache revalidation delay, errors visible.
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

USER app
EXPOSE 8000

# ---- prod: code baked into the image, no dev dependencies ----
FROM base AS prod

ARG UID=1000
ARG GID=1000

RUN addgroup -g "${GID}" app \
    && adduser -D -u "${UID}" -G app -h /home/app app \
    && mkdir -p /config /data \
    && chown -R app:app /config /data \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --chown=app:app api/ /app/
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && php artisan package:discover --ansi \
    && chown -R app:app /app/vendor /app/bootstrap/cache /app/storage

USER app
EXPOSE 8000
