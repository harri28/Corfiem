FROM php:8.1-apache

# Dependencias del sistema y extensiones PHP
RUN apt-get update && apt-get install -y \
        libpq-dev \
        libonig-dev \
        libcurl4-openssl-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        curl \
        fileinfo \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configuración PHP
RUN { \
        echo "date.timezone = America/Lima"; \
        echo "upload_max_filesize = 20M"; \
        echo "post_max_size = 25M"; \
        echo "memory_limit = 256M"; \
    } > /usr/local/etc/php/conf.d/corfiem.ini

# Habilitar mod_rewrite
RUN a2enmod rewrite

# 🔥 IMPORTANTE: cambiar Apache a puerto 8080
RUN sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Copiar la app
COPY . .

# Permisos y carpeta uploads
RUN mkdir -p uploads/ \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Puerto para Cloud Run
EXPOSE 8080

CMD ["apache2-foreground"]