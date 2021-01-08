FROM php:8.0.0-fpm-alpine

WORKDIR /app

# Get composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . /app

CMD sh -c "composer install && composer test"
