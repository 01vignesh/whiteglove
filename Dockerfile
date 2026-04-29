FROM php:8.2-apache

WORKDIR /var/www/html

# Required PHP extensions for WhiteGlove.
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite for front-controller style routing if needed.
RUN a2enmod rewrite

# Point Apache document root to /public.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri "s!/var/www/!${APACHE_DOCUMENT_ROOT}/!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy project.
COPY . /var/www/html

# Runtime writable dirs.
RUN mkdir -p /var/www/html/public/uploads/clients \
    /var/www/html/public/uploads/providers \
    /var/www/html/public/uploads/admins \
    && chown -R www-data:www-data /var/www/html/public/uploads

EXPOSE 80

