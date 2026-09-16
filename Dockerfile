FROM php:8.3-fpm

# Update packages and install Nginx + supervisord + curl (for health check)
RUN apt-get update && apt-get install -y nginx supervisor curl && rm -rf /var/lib/apt/lists/* && \
    mkdir -p /var/run/php-fpm /var/log/nginx /var/log/supervisor && \
    chown -R www-data:www-data /var/run/php-fpm /var/log/nginx

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo_mysql

# Configure PHP for large uploads (All-in-One WP Migration)
RUN echo "upload_max_filesize = 300M" > /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 300M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time = 600" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_input_time = 600" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "default_socket_timeout = 300" >> /usr/local/etc/php/conf.d/uploads.ini

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

# Download JWT Auth plugin from GitHub
RUN mkdir -p /tmp/jwt-auth && \
    cd /tmp/jwt-auth && \
    curl -L https://github.com/usefulteam/jwt-auth/archive/refs/heads/master.zip -o jwt-auth.zip && \
    unzip -q jwt-auth.zip && \
    rm jwt-auth.zip && \
    mv jwt-auth-master /tmp/jwt-auth-plugin

# Copy WordPress files
COPY . /var/www/html

# Copy JWT Auth plugin to plugins directory
RUN cp -r /tmp/jwt-auth-plugin /var/www/html/wp-content/plugins/jwt-auth && \
    rm -rf /tmp/jwt-auth*

# Set permissions for Nginx and PHP-FPM
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/html/wp-content

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
