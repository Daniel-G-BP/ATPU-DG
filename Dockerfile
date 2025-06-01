FROM php:8.2-apache

# Zkopírování vlastní konfigurace PHP
COPY php.ini /usr/local/etc/php/

# Povolení modulu rewrite pro Apache
RUN a2enmod rewrite

# Instalace PHP rozšíření potřebných pro MySQL + PhpSpreadsheet
RUN apt-get update && apt-get install -y \
    unzip \
    libzip-dev \
    libxml2-dev \
    libpng-dev \
    libonig-dev \
    && docker-php-ext-install pdo pdo_mysql zip xml mbstring gd

# Instalace Composeru
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Nastavení pracovní složky
WORKDIR /var/www/html

# Zkopírování obsahu projektu do kontejneru
COPY . /var/www/html

# Instalace PHP knihoven (včetně PhpSpreadsheet)
RUN composer install
