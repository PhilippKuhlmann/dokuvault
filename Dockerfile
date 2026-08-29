# Ein Container mit nginx und php-fpm, gehalten von supervisord.
#
# Sauberer waeren zwei Container - einer je Prozess. Dann waere das
# veroeffentlichte Abbild allein aber nicht lauffaehig, und genau das soll es
# sein: "docker run" und es steht.
#
# nginx liefert CSS, JavaScript und Bilder selbst aus und laesst PHP nur an die
# Anfragen, die es braucht. Der eingebaute Server aus "artisan serve", den
# dieses Bild vorher benutzte, ist ein Entwicklungswerkzeug.

# ---------------------------------------------------------------- PHP-Pakete
# --platform=$BUILDPLATFORM: Diese Stufe laeuft immer auf der Architektur des
# Bauknechts, nicht auf der des Ziels. Was hier entsteht, ist PHP-Quelltext und
# damit architekturunabhaengig - fuer arm64 emuliert bauen zu lassen, kostet
# nur Zeit. Beim Multi-Arch-Bau blieb "npm ci" unter QEMU praktisch stehen.
FROM --platform=$BUILDPLATFORM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Ohne --no-dev: die Demo-Daten brauchen fakerphp/faker. --no-scripts, weil
# artisan hier noch nicht vollstaendig vorliegt.
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

# ------------------------------------------------------------------ Frontend
# Ebenso: Vite erzeugt CSS und JavaScript, beides ohne Architekturbezug.
FROM --platform=$BUILDPLATFORM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
# Der Frontend-Build greift an zwei Stellen nach vendor/: resources/js/app.js
# importiert Livewires ESM-Bundle von dort, und tailwind.config.js durchsucht
# die Pagination-Views des Frameworks. Deshalb das ganze Verzeichnis statt
# einzelner Pfade - sonst bricht der Build erneut, sobald ein Bezug dazukommt.
COPY --from=vendor /app/vendor vendor
RUN npm run build

# ------------------------------------------------------------------- Laufzeit
# Ohne --platform: Nur diese Stufe ist architekturabhaengig - hier werden die
# PHP-Erweiterungen uebersetzt, und das Ergebnis muss zum Ziel passen.
#
# fpm statt cli: Ausgeliefert wird ueber nginx. Der eingebaute Server aus
# "artisan serve" ist ein Entwicklungswerkzeug - einzelthreadig, ohne Opcache
# und mit PHP fuer jede noch so kleine CSS-Datei.
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        freetype libjpeg-turbo libpng libzip \
        nginx supervisor \
    && apk add --no-cache --virtual .bau \
        freetype-dev libjpeg-turbo-dev libpng-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip opcache \
    && apk del .bau

WORKDIR /app

COPY --from=vendor /app/vendor vendor
COPY . .
COPY --from=frontend /app/public/build public/build

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-interaction --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/dokuvault.ini

# nginx will diese Verzeichnisse vorfinden; im Alpine-Paket fehlen sie, wenn
# der Dienst nie ueber das init-System gestartet wurde.
RUN mkdir -p /run/nginx /var/lib/nginx/tmp \
    && chown -R www-data:www-data /var/lib/nginx

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000
ENTRYPOINT ["entrypoint"]
