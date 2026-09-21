#!/bin/bash
set -e

# Create .env file from environment variables
echo "Creating .env file from environment variables..."
cat > /var/www/html/.env <<EOF
# Database
DB_HOST=${DB_HOST:-localhost}
DB_NAME=${DB_NAME:-ravie_woocomerce}
DB_USER=${DB_USER:-root}
DB_PASSWORD=${DB_PASSWORD:-}

# Gmail SMTP (Development)
GMAIL_USERNAME=${GMAIL_USERNAME:-achraf.jarhou@laplateforme.io}
GMAIL_PASSWORD=${GMAIL_PASSWORD:-}

# Brevo API (Production)
BREVO_API_KEY=${BREVO_API_KEY:-}

# WordPress
WP_DEBUG=true
WP_DEBUG_LOG=true
EOF

chmod 600 /var/www/html/.env

# Fix permissions for WordPress directories
echo "Fixing file permissions..."
mkdir -p /var/www/html/wp-content/uploads /var/www/html/wp-content/themes /var/www/html/wp-content/plugins /var/www/html/wp-content/upgrade
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 775 /var/www/html/wp-content
chmod -R 775 /var/www/html/wp-content/uploads
chmod -R 775 /var/www/html/wp-content/themes
chmod -R 775 /var/www/html/wp-content/plugins
chmod -R 775 /var/www/html/wp-content/upgrade

# Use PORT environment variable if set, otherwise default to 8080
PORT=${PORT:-8080}

echo "Starting with PORT=$PORT"

# Function to install plugins after WordPress is ready
install_plugins() {
  sleep 45
  cd /var/www/html

  # Wait for database to be ready
  echo "Waiting for WordPress to be ready..."
  for i in {1..60}; do
    if /usr/local/bin/wp core is-installed --allow-root 2>/dev/null; then
      echo "WordPress is ready!"
      break
    fi
    echo "Attempt $i/60: Waiting for WordPress..."
    sleep 2
  done

  echo "Installing essential WooCommerce plugins..."

  # Install and activate plugins
  /usr/local/bin/wp plugin install woocommerce --activate --allow-root 2>&1 || echo "WooCommerce install attempt complete"
  /usr/local/bin/wp plugin install advanced-custom-fields --activate --allow-root 2>&1 || true
  /usr/local/bin/wp plugin install akismet --activate --allow-root 2>&1 || true
  /usr/local/bin/wp plugin install jetpack --activate --allow-root 2>&1 || true
  /usr/local/bin/wp plugin install woo-stripe-payment --activate --allow-root 2>&1 || true

  # Activate JWT Auth plugin (should already be in repo)
  echo "Activating JWT Auth plugin..."
  /usr/local/bin/wp plugin activate jwt-authentication-for-wp-rest-api --allow-root 2>&1 || true

  # Force activate all required plugins
  echo "Ensuring all plugins are activated..."
  /usr/local/bin/wp plugin activate --all --allow-root 2>&1 || true

  echo "Plugins installation complete"
}

# Run plugin installation in background
install_plugins &

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
    error_log /proc/self/fd/2 warn;

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

        location ~ ^/wp-content/uploads/ {
            add_header 'Access-Control-Allow-Origin' '*' always;
            add_header 'Access-Control-Allow-Methods' 'GET, HEAD, OPTIONS' always;
            add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization' always;
            add_header 'Access-Control-Max-Age' '86400' always;
            if (\$request_method = 'OPTIONS') {
                return 204;
            }
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
