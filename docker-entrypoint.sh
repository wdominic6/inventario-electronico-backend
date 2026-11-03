#!/bin/sh
# Asegura que PHP-FPM (usuario www-data) pueda escribir en storage/ y bootstrap/cache,
# necesario cuando el código se monta como volumen desde el host.
set -e

cd /var/www/html

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/framework/testing storage/logs bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

exec docker-php-entrypoint "$@"
