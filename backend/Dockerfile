FROM serversideup/php:8.4-fpm-nginx-alpine

USER root

# intl is needed by Laravel's Number/Date helpers
RUN install-php-extensions intl

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .

RUN composer run-script post-autoload-dump || true \
 && php artisan config:clear

ENTRYPOINT ["sh", "docker/entrypoint.sh"]
