# Production-accurate Dockerfile - mirrors NFSN server exactly.
# NFSN actual versions (checked 2026-05-06): PHP 8.3.29-nfsn1 (CLI default), PHP 8.4.16-nfsn1 (web server), Node v22.19.0, MariaDB 10.11.14, FreeBSD 14.3 amd64
# NOTE: NFSN web server is configured to PHP 8.4 in control panel. composer.json requires >=8.4.
# This Dockerfile uses PHP 8.4 to match the production web server version.
#
# Apache layout (identical pattern to NFSN):
#   /var/www/deploy/webroot  <- outer symlink (created by deploy:update_webroot_symlink)
#   /var/www/deploy/current  <- Deployer current release symlink
#   /var/www/deploy/releases <- numbered releases
#   /var/www/deploy/shared   <- shared config/tmp/logs
#
# SSH: OpenSSH server runs on port 22 (mapped to host port 8022 by docker-compose).
# Deployer connects via SSH (same code path as production NFSN deployment).
# The deploy key is in .local/docker/deploy_local_key (gitignored, local test only).
FROM php:8.4-apache

# Install required PHP extensions + Node.js 22 LTS + OpenSSH server
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    git \
    curl \
    rsync \
    openssh-server \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        opcache \
        zip \
        intl \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure opcache
RUN echo 'opcache.enable=1\n\
opcache.memory_consumption=128\n\
opcache.interned_strings_buffer=8\n\
opcache.max_accelerated_files=10000\n\
opcache.revalidate_freq=60\n\
opcache.fast_shutdown=1\n\
opcache.enable_cli=0\n\
opcache.validate_timestamps=1' >> /usr/local/etc/php/conf.d/opcache.ini

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure OpenSSH server
# The authorized_keys file is mounted from the host at runtime (see docker-compose.yml volumes).
# Each developer generates their own key: ssh-keygen -t ed25519 -f .local/docker/deploy_local_key -N ""
# Running as root is fine for this local-only test container.
RUN mkdir -p /root/.ssh && chmod 700 /root/.ssh \
    && mkdir -p /var/run/sshd \
    && sed -i 's/#PermitRootLogin prohibit-password/PermitRootLogin yes/' /etc/ssh/sshd_config \
    && sed -i 's/#PubkeyAuthentication yes/PubkeyAuthentication yes/' /etc/ssh/sshd_config \
    && ssh-keygen -A  # Generate host keys

# Configure Apache to serve from the outer webroot symlink - mirrors NFSN exactly.
# On NFSN: Apache docroot = /home/public/webroot (symlink -> current/webroot)
# In Docker: Apache docroot = /var/www/deploy/webroot (symlink -> current/webroot)
# deploy:update_webroot_symlink creates this symlink on every deploy/rollback.
# FollowSymLinks is required: webroot/, current/, and tmp/ are all symlinks.
RUN echo '<VirtualHost *:80>\n\
    ServerAdmin webmaster@localhost\n\
    DocumentRoot /var/www/deploy/webroot\n\
    \n\
    <Directory /var/www/deploy>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    \n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Copy entrypoint
COPY docker/local/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
