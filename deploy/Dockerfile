# syntax=docker/dockerfile:1

FROM node:22-bookworm-slim AS landing

WORKDIR /src

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY angular.json tsconfig.json tsconfig.app.json ./
COPY src ./src
COPY public ./public

RUN npx ng build --base-href=/


FROM dunglas/frankenphp:1-php8.3 AS php-base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    SERVER_NAME=:80

RUN install-php-extensions intl pdo_mysql zip gd exif opcache apcu bcmath sodium

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer


FROM php-base AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip zip curl ca-certificates gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g yarn \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_SECRET=build-time-only \
    DATABASE_URL="mysql://build:build@127.0.0.1:3306/build?serverVersion=8.0&charset=utf8mb4"

COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

COPY backend/ ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

RUN yarn install --frozen-lockfile --ignore-engines --non-interactive \
    && yarn encore production \
    && rm -rf node_modules

RUN php -d memory_limit=-1 bin/console sylius:install:assets --no-interaction \
    && php -d memory_limit=-1 bin/console cache:clear --no-interaction \
    && php -d memory_limit=-1 bin/console cache:warmup --no-interaction

# Landing zastepuje zahashowane statyki z repo — inaczej w obrazie zostalyby
# oba buildy, a index.html wskazuje tylko na najnowszy.
COPY --from=landing /src/dist/oma/browser/ /landing/
RUN find public -maxdepth 1 -type f \
        \( -name 'main-*.js' -o -name 'chunk-*.js' -o -name 'styles-*.css' \
        -o -name 'polyfills-*.js' -o -name 'index.html' \) -delete \
    && rm -rf public/assets/img \
    && cp -a /landing/. public/ \
    && rm -rf /landing


FROM php-base AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        default-mysql-client curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY backend/docker/frankenphp/php.ini /usr/local/etc/php/conf.d/zz-oma.ini
# Musi byc "zzz-", zeby wczytac sie PO zz-oma.ini i nadpisac limity opcache.
COPY deploy/php-prod.ini /usr/local/etc/php/conf.d/zzz-oma-prod.ini
COPY backend/docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY deploy/entrypoint.sh /usr/local/bin/oma-entrypoint
RUN chmod +x /usr/local/bin/oma-entrypoint

WORKDIR /app

COPY --from=builder /app ./

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_RUNTIME='Runtime\FrankenPhpSymfony\Runtime'

ENTRYPOINT ["oma-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
