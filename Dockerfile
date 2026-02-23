FROM php:8.3-fpm-alpine

RUN apk add --no-cache postgresql-libs postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

COPY . .

RUN chown -R www-data:www-data /var/www/html/data

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
