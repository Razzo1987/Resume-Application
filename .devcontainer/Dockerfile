# Environment for GitHub Codespaces (and VS Code Dev Containers): PHP 8.4 with the required extensions, Composer, Node
FROM mcr.microsoft.com/devcontainers/php:1-8.4-bookworm

# The image includes the Yarn apt repository, whose GPG key has expired: apt-get update would fail.
# Yarn is not needed (npm is used), so the repository is removed.
RUN rm -f /etc/apt/sources.list.d/yarn.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" intl zip gd pdo_mysql exif bcmath \
    && rm -rf /var/lib/apt/lists/*
