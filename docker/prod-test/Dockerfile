# Production-optimized Dockerfile using official PHP image
FROM php:8.4-apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        opcache \
        zip \
        intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure opcache (production settings)
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

# Configure Apache VirtualHost
RUN echo '<VirtualHost *:80>\n\
    ServerAdmin webmaster@localhost\n\
    DocumentRoot /var/www/html/webroot\n\
    \n\
    <Directory /var/www/html/webroot>\n\
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

# Don't copy files - use mounted volume for testing
# COPY . /var/www/html/

# Set permissions (runs as www-data by default)
# RUN chown -R www-data:www-data /var/www/html

CMD ["apache2-foreground"]
