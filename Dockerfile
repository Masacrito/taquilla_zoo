# syntax=docker/dockerfile:1

# ─────────────────────────────────────────────────────────────────────────
#  Imagen de la aplicación (staging / producción)
#
#  El código va DENTRO de la imagen, no montado: lo que se despliega es un
#  artefacto inmutable. Para desarrollo local sigue sirviendo `artisan serve`.
#
#  OJO con dos cosas que a propósito NO ocurren aquí:
#
#   · No se cachea la configuración en el build. `config:cache` congela los
#     valores de entorno del momento en que se construye, y en el build no
#     existen todavía. Va en el entrypoint, ya con el .env inyectado.
#
#   · No se genera APP_KEY. Esta aplicación firma los códigos QR con ella
#     (QrTokenService) y de ella cuelga el secreto del webhook si no se
#     define aparte. Una llave nueva invalida todos los QR ya emitidos, así
#     que se exige que venga de fuera y sea estable.
# ─────────────────────────────────────────────────────────────────────────

# ── Base común: PHP con las extensiones que la aplicación declara ────────
FROM php:8.2-fpm-alpine AS base

# gd:        escritor PNG de endroid/qr-code y rasterizado de dompdf
# pdo_pgsql: PostgreSQL
# pcntl:     señales del worker de colas, para que termine el job en curso
#            antes de morir en un redespliegue
RUN apk add --no-cache \
        libpng libjpeg-turbo freetype libpq oniguruma icu-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS libpng-dev libjpeg-turbo-dev freetype-dev \
        postgresql-dev oniguruma-dev icu-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        gd pdo pdo_pgsql mbstring bcmath pcntl intl opcache \
    && apk del .build-deps

WORKDIR /var/www

# ── Dependencias PHP ────────────────────────────────────────────────────
# Se instalan sobre la imagen base y no sobre la de composer, para que las
# extensiones declaradas en composer.json se verifiquen de verdad. Si al
# servidor le faltara gd, esto revienta aquí y no cuando alguien pague.
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist \
        --no-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ── Assets ──────────────────────────────────────────────────────────────
# public/build está en .gitignore, así que el bundle se arma aquí. Sin este
# paso el sitio sale sin estilos.
FROM node:20-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
COPY resources/views ./resources/views
RUN npm run build

# ── Imagen final ────────────────────────────────────────────────────────
FROM base AS runtime

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-taquilla.ini

COPY --chown=www-data:www-data . .
COPY --from=vendor  --chown=www-data:www-data /var/www/vendor       ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build    ./public/build

# Laravel escribe caché de vistas, logs y archivos de framework. El resto de
# la aplicación queda de solo lectura para www-data.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/entrypoint

USER www-data

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ── Servidor web ────────────────────────────────────────────────────────
# nginx con los estáticos YA dentro de la imagen, en vez de compartirlos por
# un volumen. Un volumen con nombre montado en nginx arrancaría vacío —la
# imagen de nginx no trae nada en esa ruta— y el sitio saldría sin estilos
# hasta que otro contenedor lo poblara primero. Así no hay orden que cuidar.
FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=runtime /var/www/public /var/www/public

EXPOSE 80
