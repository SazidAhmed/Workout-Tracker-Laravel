FROM php:8.4-apache

# Install system dependencies and PHP extensions commonly needed by Laravel.
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Enable Apache rewrite support for Laravel routing.
RUN a2enmod rewrite

WORKDIR /var/www/html

# Install Composer binary.
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

EXPOSE 8000

# Point Apache to Laravel public directory and run on port 8000.
RUN sed -i 's/DocumentRoot \/var\/www\/html/DocumentRoot \/var\/www\/html\/public/g' /etc/apache2/sites-available/000-default.conf
RUN sed -i 's/Listen 80/Listen 8000/g' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:8000>/g' /etc/apache2/sites-available/000-default.conf

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
