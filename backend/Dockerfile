# ─── Stage 1 : dépendances Composer ──────────────────────────────────────────
FROM php:8.3-fpm-alpine AS deps

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    linux-headers \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    autoconf g++ make \
    zip unzip git

RUN pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo pdo_pgsql pgsql \
        zip opcache pcntl bcmath intl gd exif

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-req=php

COPY . .
RUN mkdir -p bootstrap/cache storage/logs \
    && composer dump-autoload --optimize --no-interaction --ignore-platform-req=php

# ─── Stage 2 : image de production ───────────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

RUN apk add --no-cache \
    nginx supervisor \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    linux-headers \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    autoconf g++ make

RUN pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo pdo_pgsql pgsql \
        zip opcache pcntl bcmath intl gd exif

WORKDIR /app

COPY --from=deps /app /app

COPY docker/php.ini         /usr/local/etc/php/php.ini
COPY docker/opcache.ini     /usr/local/etc/php/conf.d/99-opcache.ini
COPY docker/php-fpm.conf    /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/nginx.conf      /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start.sh        /start.sh

RUN chmod +x /start.sh \
    && mkdir -p /run/nginx /var/lib/nginx/tmp/client_body \
    && chmod 755 /var/lib/nginx \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /var/lib/nginx/tmp \
    && chmod -R 775 /app/storage /app/bootstrap/cache

EXPOSE 80

CMD ["/start.sh"]
