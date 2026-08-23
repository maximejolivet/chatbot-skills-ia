FROM php:8.4-cli

WORKDIR /app

RUN apt-get update && apt-get install -y \
    default-libmysqlclient-dev \
    libicu-dev \
    libzip-dev \
    unzip \
    git \
    curl \
    python3 \
    python3-venv \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_mysql intl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# uv -- runs the Python scripts bundled with Composer-installed AI agent
# skills (e.g. netresearch/php-modernization-skill's introspect.py/
# verify_php_project.py/modernize_loop.py, see vendor/agent-skills/). Not a
# PHP/Symfony runtime dependency -- purely a dev-tooling addition for
# whichever agent works in this container.
ENV UV_INSTALL_DIR=/usr/local/bin
RUN curl -LsSf https://astral.sh/uv/install.sh | sh

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock composer.skills.lock ./
RUN composer install --no-interaction --no-scripts --no-progress

COPY . .
RUN composer dump-autoload --optimize

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
