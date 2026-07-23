FROM php:8.3-fpm

WORKDIR /var/www/html

# libwebp-dev + configure gd : requis pour la conversion WebP à l'upload
# (aligné sur Dockerfile.prod — sans ça, imagewebp() n'existe pas et tout
# upload d'image via Intervention Image plante en 500).
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    && docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    xml \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY infra/docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache-recommended.ini

COPY backend/composer.json backend/composer.lock ./
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

CMD ["php-fpm"]
