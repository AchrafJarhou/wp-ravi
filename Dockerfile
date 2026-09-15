FROM php:8.3-fpm

# Update packages and install Nginx + supervisord + curl (for health check)
RUN apt-get update && apt-get install -y nginx supervisor curl && rm -rf /var/lib/apt/lists/* && \
    mkdir -p /var/run/php-fpm /var/log/supervisor && \
    chown -R www-data:www-data /var/run/php-fpm

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo_mysql

# Copy WordPress files
COPY . /var/www/html

# Copy configurations
COPY nginx.conf /etc/nginx/nginx.conf
COPY php-fpm.conf /usr/local/etc/php-fpm.conf
COPY supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost/wp-json/ || exit 1

EXPOSE 80

# Run with supervisord
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
