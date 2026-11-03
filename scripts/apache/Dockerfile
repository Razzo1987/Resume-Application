# Apache 2.4 + PHP 8.4 (mod_php) with the extensions the app requires, like a typical shared hosting: used by the
# release workflow to install the package with the web installer and run scripts/smoke-test.sh and the end-to-end
# tests of the site on it. The webroot is mounted on /var/www/html.
FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp \
    && docker-php-ext-install -j"$(nproc)" intl zip gd pdo_mysql bcmath exif \
    && a2enmod rewrite headers expires deflate \
    && printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-enabled/resume-app.conf \
    && rm -rf /var/lib/apt/lists/*
