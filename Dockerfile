FROM php:8.5-apache

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y libsqlite3-dev cron \
    && docker-php-ext-install pdo_sqlite pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf

RUN echo "* * * * * root cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1" \
    > /etc/cron.d/herold-scheduler \
    && chmod 0644 /etc/cron.d/herold-scheduler

WORKDIR /var/www/html
COPY . .
