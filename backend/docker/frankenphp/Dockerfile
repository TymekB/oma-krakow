FROM dunglas/frankenphp:1-php8.3

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    SERVER_NAME=:80

RUN install-php-extensions intl pdo_mysql zip gd exif opcache apcu

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip zip curl ca-certificates gnupg default-mysql-client \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g yarn \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-oma.ini
COPY Caddyfile /etc/frankenphp/Caddyfile
COPY entrypoint.sh /usr/local/bin/oma-entrypoint

RUN chmod +x /usr/local/bin/oma-entrypoint

WORKDIR /app

ENTRYPOINT ["oma-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
