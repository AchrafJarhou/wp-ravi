#!/bin/bash
set -e

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
    client_max_body_size 20M;

    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    access_log /proc/self/fd/1;
    error_log /proc/self/fd/2 warn;

    gzip on;

    upstream php_fpm {
        server unix:/var/run/php-fpm.sock;
    }

    server {
        listen $PORT;
        server_name _;
        root /var/www/html;

        location = /health {
            access_log off;
            return 200 "OK\n";
            add_header Content-Type text/plain;
        }

        location = /test.php {
            try_files \$uri =404;
            fastcgi_pass php_fpm;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            fastcgi_param QUERY_STRING \$query_string;
        }

        location = /test-simple.php {
            try_files \$uri =404;
            fastcgi_pass php_fpm;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            fastcgi_param QUERY_STRING \$query_string;
        }

        location = /debug-cors.php {
            try_files \$uri =404;
            fastcgi_pass php_fpm;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            fastcgi_param QUERY_STRING \$query_string;
        }

        location / {
            try_files \$uri \$uri/ /index.php?\$args;
        }

        location ~ \.php\$ {
            try_files \$uri =404;
            fastcgi_pass php_fpm;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            fastcgi_param QUERY_STRING \$query_string;
        }
    }
}
EOF

echo "Nginx config created for port $PORT"
echo "Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
