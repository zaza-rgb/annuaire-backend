FROM php:8.3-cli-alpine

# Installation des dépendances système nécessaires à Laravel et SQLite
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    sqlite-dev \
    oniguruma-dev \
    icu-dev

# Installation et configuration des extensions PHP requises
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_sqlite \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        bcmath \
        intl \
        opcache

# Installation de Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copie des fichiers de configuration des dépendances pour bénéficier du cache Docker
COPY composer.json composer.lock ./

# Installation des dépendances Composer (sans scripts pour l'instant)
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copie de l'intégralité du code source
COPY . .

# Finalisation de l'autoload Composer
RUN composer dump-autoload --optimize

# Permissions sur les dossiers d'écriture
RUN chmod +x /app/docker-entrypoint.sh \
    && chmod -R 775 /app/storage /app/bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["/app/docker-entrypoint.sh"]
