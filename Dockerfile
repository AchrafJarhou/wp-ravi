FROM php:8.3-fpm

# Update packages and install Nginx + supervisord + curl (for health check)
RUN apt-get update && apt-get install -y nginx supervisor curl && rm -rf /var/lib/apt/lists/* && \
    mkdir -p /var/run/php-fpm /var/log/nginx /var/log/supervisor && \
    chown -R www-data:www-data /var/run/php-fpm /var/log/nginx

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo_mysql

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

# Copy WordPress files
COPY . /var/www/html

# Copy configurations
COPY php-fpm.conf /usr/local/etc/php-fpm.conf
COPY supervisord.conf /etc/supervisord.conf
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Health check - test if Nginx is responding on PORT
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
  CMD curl -f http://localhost:${PORT:-8080}/health || exit 1

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
