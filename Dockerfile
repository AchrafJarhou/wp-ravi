FROM dunglas/frankenphp:latest-php8.3

# Installer les extensions PHP nécessaires pour WordPress
RUN docker-php-ext-install mysqli pdo_mysql

# Copier les fichiers WordPress
COPY . /app

# Copier la configuration Caddy
COPY Caddyfile /etc/caddy/Caddyfile

# Répertoire de travail
WORKDIR /app
