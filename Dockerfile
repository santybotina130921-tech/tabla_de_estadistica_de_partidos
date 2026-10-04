FROM php:8.2-apache

# 1. Instalar dependencias del sistema necesarias para PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. Instalar las extensiones de PHP para PostgreSQL y PDO
RUN docker-php-ext-install pdo pdo_pgsql pgsql

# 3. Habilitar mod_rewrite de Apache (útil para enrutamiento y URLs amigables)
RUN a2enmod rewrite

# 4. Copiar los archivos de tu proyecto al contenedor
COPY . /var/www/html/

# 5. Configurar permisos apropiados
RUN chown -R www-data:www-data /var/www/html

# Exponer el puerto 80 que usará Render
EXPOSE 80