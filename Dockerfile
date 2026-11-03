FROM php:8.4-fpm-alpine

# Instalar dependencias del sistema y extensiones requeridas por Laravel 13 y MySQL 8.4
RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd intl

# Copiar Composer 2 oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Entrypoint que corrige permisos de storage/ y bootstrap/cache (volumen montado desde el host).
# sed elimina posibles CRLF si el archivo se editó en Windows.
COPY docker-entrypoint.sh /usr/local/bin/app-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/app-entrypoint && chmod +x /usr/local/bin/app-entrypoint

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]