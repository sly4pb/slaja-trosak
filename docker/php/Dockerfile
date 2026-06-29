FROM php:8.3-fpm

# ─── System dependencies ───────────────────────────────────
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ─── PHP extensions ────────────────────────────────────────
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        xml

# ─── Redis extension (PECL) ────────────────────────────────
RUN pecl install redis \
    && docker-php-ext-enable redis

# ─── Composer ──────────────────────────────────────────────
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# ─── Non-root user (sigurnost) ─────────────────────────────
RUN groupadd -g 1000 www \
    && useradd -u 1000 -ms /bin/bash -g www www

# ─── Working directory ─────────────────────────────────────
WORKDIR /var/www/html

# ─── Permissions ───────────────────────────────────────────
RUN chown -R www:www /var/www/html

USER www

EXPOSE 9000

CMD ["php-fpm"]
