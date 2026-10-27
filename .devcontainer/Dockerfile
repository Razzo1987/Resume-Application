# Ambiente per GitHub Codespaces (e Dev Containers di VS Code): PHP 8.4 con le estensioni richieste, Composer, Node
FROM mcr.microsoft.com/devcontainers/php:1-8.4-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev default-mysql-client \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" intl zip gd pdo_mysql exif bcmath \
    && rm -rf /var/lib/apt/lists/*
