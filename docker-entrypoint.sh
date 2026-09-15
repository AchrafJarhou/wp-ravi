#!/bin/bash

echo "[$(date)] Starting PHP-FPM..."
php-fpm -D -c /usr/local/etc/php-fpm.conf

echo "[$(date)] Waiting for PHP-FPM socket..."
for i in {1..30}; do
    if [ -S /var/run/php-fpm.sock ]; then
        echo "[$(date)] PHP-FPM socket ready!"
        break
    fi
    echo "[$(date)] Waiting... ($i/30)"
    sleep 1
done

if [ ! -S /var/run/php-fpm.sock ]; then
    echo "[$(date)] ERROR: PHP-FPM socket not created!"
    exit 1
fi

echo "[$(date)] Starting Nginx..."
exec nginx -g 'daemon off;'
