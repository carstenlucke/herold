FROM php:8.5-apache

# Apache mod_rewrite (for Laravel .htaccess)
RUN a2enmod rewrite

# PHP Extensions
RUN apt-get update && apt-get install -y libsqlite3-dev gosu \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Apache DocumentRoot on public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf

WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader

COPY docker-entrypoint.sh /usr/local/bin/
ENTRYPOINT ["docker-entrypoint.sh"]
