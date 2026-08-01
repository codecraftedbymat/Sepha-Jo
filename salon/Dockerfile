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

WORKDIR /var/www/html
COPY . /var/www/html

# PHPMailer, pour l'envoi SMTP des confirmations.
#
# L'installation est TOLÉRANTE À L'ÉCHEC : si Composer ne s'exécute pas,
# le build se poursuit. Le site fonctionne alors normalement, seuls les
# e-mails ne partent pas.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    || echo "AVERTISSEMENT : Composer a echoue, les e-mails ne partiront pas."

RUN chown -R www-data:www-data /var/www/html

# =====================================================================
#  Configuration Apache
#
#  On écrit un hôte virtuel complet plutôt que de modifier les fichiers
#  livrés avec l'image : les `sed` trop larges finissaient par toucher
#  les fichiers de chargement des modules, d'où l'erreur « More than one
#  MPM loaded ».
#
#  La racine web est public/ : includes/ et api/ restent hors du dossier
#  servi, donc inaccessibles depuis un navigateur.
# =====================================================================
RUN printf '%s\n' \
    '<VirtualHost *:${PORT}>' \
    '    DocumentRoot /var/www/html/public' \
    '    <Directory /var/www/html/public>' \
    '        Options -Indexes +FollowSymLinks' \
    '        AllowOverride All' \
    '        Require all granted' \
    '    </Directory>' \
    '    ErrorLog /proc/self/fd/2' \
    '    CustomLog /proc/self/fd/1 combined' \
    '</VirtualHost>' \
    > /etc/apache2/sites-available/000-default.conf

# Railway impose le port d'écoute via la variable PORT.
# On la déclare à Apache, puis on remplace la directive Listen.
CMD ["sh", "-c", "export PORT=${PORT:-80} && echo \"Listen ${PORT}\" > /etc/apache2/ports.conf && apache2-foreground"]
