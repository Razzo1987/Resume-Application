# Ambiente per GitHub Codespaces (e Dev Containers di VS Code): PHP 8.4 con le estensioni richieste, Composer, Node
FROM mcr.microsoft.com/devcontainers/php:1-8.4-bookworm

# L'immagine include il repository apt di Yarn, la cui chiave GPG è scaduta: apt-get update fallirebbe.
# Yarn non serve (si usa npm), quindi il repository si toglie.
RUN rm -f /etc/apt/sources.list.d/yarn.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" intl zip gd pdo_mysql exif bcmath \
    && rm -rf /var/lib/apt/lists/*
