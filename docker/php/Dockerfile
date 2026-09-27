# Canonical PHP+Composer+Node toolchain image for this skill catalog.
#
# Used two ways:
#   1. As-is, by .claude/skills/_shared/scripts/run-skill-php.sh, to run the
#      one-shot PHP orchestration scripts under .claude/skills/*/scripts/*.php
#      (composer create-project, composer install/require, npm install) with
#      zero PHP/Composer/Node installed on the host.
#   2. Copied verbatim into the generated project as docker/php/Dockerfile by
#      config-project-fullstack, where it backs the `php` service in the
#      project's own root docker-compose.yml (composer install, phpunit,
#      bin/console, doctrine migrations, the dev server — everything).
#
# UID/GID are build args so files created inside the container (vendor/,
# composer.lock, migrations, node_modules/) are owned by the host user, not
# root — both callers pass the current host `id -u`/`id -g` at build time.
FROM php:8.3-cli-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libzip-dev \
        nodejs \
        npm \
        oniguruma-dev \
        postgresql-dev \
        unzip \
        ${PHPIZE_DEPS} \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
        pgsql \
        zip \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && addgroup -g "${GID}" app \
    && adduser -D -u "${UID}" -G app app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

USER app
WORKDIR /app
ENV COMPOSER_HOME=/home/app/.composer
