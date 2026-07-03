FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip nginx supervisor gettext-base \
        libpq-dev libmariadb-dev libonig-dev libxml2-dev \
        libcurl4-openssl-dev libzip-dev \
        libpng-dev libjpeg-dev libfreetype6-dev \
        libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql pdo_pgsql mbstring xml bcmath curl zip gd intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP upload limits stay above Laravel's 100MB workflow-file validation limit.
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

COPY package.json package-lock.json .npmrc ./
RUN npm install

COPY . .

RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi 2>/dev/null || true

RUN npm run build

COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf \
    && mkdir -p /run/nginx \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY start.sh /app/start.sh
RUN chmod +x /app/start.sh

EXPOSE 8080

CMD ["bash", "/app/start.sh"]
