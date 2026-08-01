# =====================================================================
#  Image de déploiement — Apache + PHP 8.2
#  Railway détecte ce Dockerfile et l'utilise automatiquement.
# =====================================================================

FROM php:8.2-apache

# unzip et libzip : nécessaires à Composer pour décompresser les paquets.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# La racine web est public/ : includes/ et api/ restent hors du web,
# donc inaccessibles depuis un navigateur.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . /var/www/html

# PHPMailer, pour l'envoi SMTP des confirmations.
#
# L'installation est TOLÉRANTE À L'ÉCHEC : si Composer ne s'exécute pas,
# le build se poursuit. Le site fonctionne alors normalement, seuls les
# e-mails ne partent pas — ce que la page de diagnostic signale
# clairement. Un problème d'envoi ne doit pas empêcher le déploiement.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    || echo "AVERTISSEMENT : Composer a echoue, les e-mails ne partiront pas."

RUN chown -R www-data:www-data /var/www/html

# Railway impose le port d'écoute via la variable PORT.
CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80/:${PORT:-80}/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
