# Utilise l'image PHP officielle avec Apache
FROM php:8.2-apache

# Installer les extensions nécessaires pour Symfony et MySQL
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd intl opcache

# Activer le module de réécriture Apache
RUN a2enmod rewrite

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier tout le code du projet dans le conteneur
COPY . /var/www/html/

# Copier Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Installer les dépendances du projet avec Composer
RUN composer install --optimize-autoloader

# Changer les permissions du répertoire de travail
RUN chown -R www-data:www-data /var/www/html

# Exposer le port 80
EXPOSE 80

# Lancer Apache au premier plan
CMD ["apache2-foreground"]
