# Bild zum Ausprobieren und fuer kleine Installationen. Ein Container, ein
# Prozess - kein nginx, kein php-fpm, kein Supervisor. Wer DokuVault fuer
# viele Nutzer betreibt, faehrt mit dem Weg aus DEPLOYMENT.md besser.

# ---------------------------------------------------------------- PHP-Pakete
FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Ohne --no-dev: die Demo-Daten brauchen fakerphp/faker. --no-scripts, weil
# artisan hier noch nicht vollstaendig vorliegt.
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

# ------------------------------------------------------------------ Frontend
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
# tailwind.config.js durchsucht auch die Pagination-Views aus dem Framework.
# Fehlen sie, fehlen hinterher deren Klassen im CSS.
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
                   vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# ------------------------------------------------------------------- Laufzeit
FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        freetype libjpeg-turbo libpng libzip \
    && apk add --no-cache --virtual .bau \
        freetype-dev libjpeg-turbo-dev libpng-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip \
    && apk del .bau

WORKDIR /app

COPY --from=vendor /app/vendor vendor
COPY . .
COPY --from=frontend /app/public/build public/build

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-interaction --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Der eingebaute Server ist einzelthreadig. Livewire schickt Anfragen waehrend
# einer laufenden Anfrage nach - ohne mehrere Arbeiter blockiert die Oberflaeche.
ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 8000
ENTRYPOINT ["entrypoint"]
