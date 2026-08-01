# =====================================================================
#  Image de déploiement — Apache + PHP 8.2
#  Railway détecte ce Dockerfile et l'utilise automatiquement.
# =====================================================================

FROM php:8.2-apache

# Extensions nécessaires à PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

# La racine web est public/ : includes/ et api/ restent hors du web,
# donc inaccessibles depuis un navigateur.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . /var/www/html

# PHPMailer (envoi SMTP des confirmations)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data /var/www/html

# Railway impose le port via la variable PORT
CMD sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf \
    && sed -i "s/:80/:${PORT:-80}/" /etc/apache2/sites-available/000-default.conf \
    && apache2-foreground
