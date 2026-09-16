#!/bin/bash
set -e

# Fix permissions for WordPress directories
echo "Fixing file permissions..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 775 /var/www/html/wp-content
chmod -R 775 /var/www/html/wp-content/uploads
chmod -R 775 /var/www/html/wp-content/themes
chmod -R 775 /var/www/html/wp-content/plugins

# Use PORT environment variable if set, otherwise default to 8080
PORT=${PORT:-8080}

echo "Starting with PORT=$PORT"

# Create nginx config with dynamic port
cat > /etc/nginx/nginx.conf <<EOF
user www-data;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 768;
}

http {
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 300M;

    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    access_log /proc/self/fd/1;
    error_log /proc/self/fd/2 debug;

    gzip on;

    upstream php_fpm {
        server unix:/var/run/php-fpm.sock;
    }

    server {
        listen $PORT;
        server_name _;
        root /var/www/html;
        index index.php index.html index.htm;
        autoindex off;

        location = /health {
            access_log off;
            return 200 "OK\n";
            add_header Content-Type text/plain;
        }

        location / {
            try_files \$uri \$uri/ /index.php\$is_args\$args;
        }

        location ~ \.php\$ {
            try_files \$uri =404;
            fastcgi_pass php_fpm;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            fastcgi_param SCRIPT_NAME \$fastcgi_script_name;
        }
    }
}
EOF

echo "Nginx config created for port $PORT"
echo "Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
