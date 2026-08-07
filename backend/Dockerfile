FROM php:8.4-cli

WORKDIR /app

RUN apt-get update && apt-get install -y \
    default-libmysqlclient-dev \
    libicu-dev \
    libzip-dev \
    unzip \
    git \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_mysql intl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-progress

COPY . .
RUN composer dump-autoload --optimize

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
