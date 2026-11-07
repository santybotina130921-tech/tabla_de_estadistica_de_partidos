FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_pgsql pgsql opcache

RUN a2enmod rewrite deflate expires headers

COPY docker/rendimiento.conf /etc/apache2/conf-available/rendimiento.conf
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
RUN a2enconf rendimiento

COPY . /var/www/html/
RUN rm -rf /var/www/html/docker \
    && mkdir -p /var/www/html/core/cache \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80