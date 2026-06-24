FROM php:8.4-fpm-alpine AS base

# Install system deps
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpq-dev \
    libxml2-dev \
    oniguruma-dev \
    curl-dev \
    icu-dev \
    zip \
    unzip \
    git

# PHP extensions
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        mbstring \
        xml \
        curl \
        intl \
        opcache \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --purge .build-deps \
    && rm -rf /tmp/pear

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---------- Dependencies ----------
FROM base AS deps

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# ---------- App ----------
FROM base AS app

# Copy vendor from deps stage
COPY --from=deps /var/www/html/vendor ./vendor

# Copy app source (excludes items in .dockerignore)
COPY . .

# Finish composer autoload
RUN composer dump-autoload --optimize --no-dev

# Laravel storage & cache dirs
RUN mkdir -p storage/framework/{cache,sessions,testing,views} \
    storage/logs \
    bootstrap/cache \
    public/sitemaps \
    && chown -R www-data:www-data storage bootstrap/cache public/sitemaps \
    && chmod -R 775 storage bootstrap/cache public/sitemaps

# Storage symlink
RUN php artisan storage:link 2>/dev/null || true

# Nginx config
COPY docker/backend/nginx.conf /etc/nginx/http.d/default.conf

# Supervisor config
COPY docker/backend/supervisord.conf /etc/supervisord.conf

# PHP production config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/backend/php-custom.ini "$PHP_INI_DIR/conf.d/99-custom.ini"
COPY docker/backend/opcache.ini "$PHP_INI_DIR/conf.d/10-opcache.ini"

# Entrypoint
COPY docker/backend/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
