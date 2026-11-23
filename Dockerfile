FROM composer:2 AS composer
FROM php:8.5-apache-bookworm

RUN set -eux; \
    curl -fsSLo /tmp/packages-microsoft-prod.deb \
        https://packages.microsoft.com/config/debian/12/packages-microsoft-prod.deb; \
    dpkg -i /tmp/packages-microsoft-prod.deb; \
    rm /tmp/packages-microsoft-prod.deb; \
    apt-get update; \
    ACCEPT_EULA=Y apt-get install -y --no-install-recommends \
        libjpeg62-turbo-dev libpng-dev libwebp-dev msodbcsql18 unixodbc-dev unzip; \
    docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr; \
    docker-php-ext-configure gd --with-jpeg --with-webp; \
    docker-php-ext-install -j "$(nproc)" gd pdo_odbc; \
    php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);'; \
    a2enmod rewrite; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

COPY app/ app/
COPY config/ config/
COPY public/ public/
COPY routes/ routes/
RUN install -d -o www-data -g www-data -m 0750 storage

ENV APP_ENV=production
