FROM php:8.4-cli-bookworm

# ── System dependencies for PHP extensions ──────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip \
        libpq-dev libmariadb-dev libonig-dev libxml2-dev \
        libcurl4-openssl-dev libzip-dev \
        libpng-dev libjpeg-dev libfreetype6-dev \
        libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql pdo_pgsql mbstring xml bcmath curl zip gd intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ── Composer ────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP upload limits stay above Laravel's 100MB workflow-file validation limit.
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# ── Node.js 22 ──────────────────────────────────────────────────────────────
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# ── PHP dependencies (cached layer) ─────────────────────────────────────────
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

# ── Node dependencies (cached layer) ────────────────────────────────────────
COPY package.json package-lock.json .npmrc ./
RUN npm install

# ── Full application source ─────────────────────────────────────────────────
COPY . .

# Finalize Laravel autoload + package discovery
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi 2>/dev/null || true

# ── Build Vite / Inertia frontend assets ────────────────────────────────────
RUN npm run build

# ── Storage & cache permissions ─────────────────────────────────────────────
RUN chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# ── Entrypoint ──────────────────────────────────────────────────────────────
COPY start.sh /app/start.sh
RUN chmod +x /app/start.sh

EXPOSE 8000

CMD ["bash", "/app/start.sh"]

