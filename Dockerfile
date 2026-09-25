FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && find /var/www/html/uploads -type d -exec chmod 775 {} \;

EXPOSE 80

CMD ["sh", "-c", "PORT=${PORT:-80}; sed -i \"s/Listen 80/Listen $PORT/; s/\\*:80/\\*:$PORT/\" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf && apache2-foreground"]