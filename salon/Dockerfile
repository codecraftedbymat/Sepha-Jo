# =====================================================================
#  Image de déploiement — Apache + PHP 8.2
#  Railway détecte ce Dockerfile et l'utilise automatiquement.
# =====================================================================

FROM php:8.2-apache

# Marqueur de version : le modifier invalide le cache Docker et force la
# reconstruction complète des couches suivantes.
ENV BUILD_REV=4

# --- Dépendances ------------------------------------------------------
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# --- Modules Apache ---------------------------------------------------
# Apache refuse de démarrer si plusieurs MPM sont chargés. Plutôt que de
# s'en remettre à a2dismod, on supprime tous les liens MPM de
# mods-enabled/ puis on n'en réactive qu'un seul.
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
    && ln -sf ../mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -sf ../mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite \
    && echo "--- MPM presents dans mods-enabled ---" \
    && ls -1 /etc/apache2/mods-enabled/ | grep -i mpm \
    && echo "--- Test de configuration ---" \
    && apache2ctl configtest

WORKDIR /var/www/html
COPY . /var/www/html

# --- PHPMailer --------------------------------------------------------
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
#  à l'exécution : la configuration est donc écrite au lancement, Apache
#  ne substituant pas les variables d'environnement dans ses fichiers.
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
    'echo "--- MPM dans mods-enabled ---"' \
    'ls -1 /etc/apache2/mods-enabled/ | grep -i mpm || echo "(aucun)"' \
    'echo "--- Test de configuration ---"' \
    'apache2ctl configtest || true' \
    'echo "Apache demarre sur le port ${PORT}"' \
    'exec apache2-foreground' \
    > /usr/local/bin/demarrer.sh \
    && chmod +x /usr/local/bin/demarrer.sh

CMD ["/usr/local/bin/demarrer.sh"]
