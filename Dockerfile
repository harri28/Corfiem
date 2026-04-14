FROM php:8.1-apache

# Dependencias del sistema y extensiones PHP requeridas por CORFIEM ERP
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

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# DocumentRoot de Apache
WORKDIR /var/www/html

# Copiar la aplicación
COPY . .

# Mover entrypoint fuera del web root y ajustar permisos
RUN mv docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chown -R www-data:www-data uploads/ \
    && chmod -R 755 uploads/

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
