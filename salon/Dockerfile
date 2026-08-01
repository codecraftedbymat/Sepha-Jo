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
# Tolérant à l'échec : un problème d'e-mail ne doit pas bloquer le déploiement.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    || echo "AVERTISSEMENT : Composer a echoue, les e-mails ne partiront pas."

RUN chown -R www-data:www-data /var/www/html

# =====================================================================
#  Démarrage
#
#  Le port est imposé par Railway via la variable PORT, connue seulement
#  à l'exécution. La configuration Apache est donc écrite au démarrage,
#  une fois la valeur réellement disponible : Apache ne substitue pas
#  les variables d'environnement dans ses fichiers de configuration.
#
#  DocumentRoot pointe sur public/ : includes/, api/ et les fichiers de
#  configuration restent hors du dossier servi.
# =====================================================================
RUN printf '%s\n' \
    '#!/bin/sh' \
    'set -e' \
    'PORT="${PORT:-80}"' \
    'echo "Listen ${PORT}" > /etc/apache2/ports.conf' \
    'cat > /etc/apache2/sites-available/000-default.conf <<EOF' \
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
    'EOF' \
    'echo "Apache demarre sur le port ${PORT}"' \
    'exec apache2-foreground' \
    > /usr/local/bin/demarrer.sh \
    && chmod +x /usr/local/bin/demarrer.sh

CMD ["/usr/local/bin/demarrer.sh"]
