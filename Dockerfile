FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libicu-dev libonig-dev libxml2-dev \
    libpng-dev libjpeg-dev libfreetype6-dev libssl-dev zlib1g-dev \
    libcurl4-openssl-dev \
 && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) pdo_mysql zip intl mbstring xml gd curl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html