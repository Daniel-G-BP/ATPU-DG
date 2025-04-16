FROM php:8.2-apache
COPY php.ini /usr/local/etc/php/

# Povolení modulu rewrite pro Apache
RUN a2enmod rewrite

# Instalace rozšíření pro práci s MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Nastavení pracovní složky
WORKDIR /var/www/html

# Zkopírování obsahu projektu do kontejneru
COPY . /var/www/html
