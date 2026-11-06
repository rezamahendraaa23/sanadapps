FROM php:8.3-fpm-bookworm

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Chromium dan font Arab dipakai Browsershot untuk PDF; pcntl dipakai Horizon.
RUN apt-get update \
    && apt-get install -y --no-install-recommends chromium fonts-noto-core git unzip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions bcmath exif gd intl opcache pcntl pdo_mysql redis zip @composer

WORKDIR /var/www/html
