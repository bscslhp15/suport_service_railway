FROM php:8.2-cli

RUN docker-php-ext-install mysqli pdo_mysql

WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

EXPOSE 80

CMD ["sh", "-c", "php scripts/bootstrap_database.php && php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]