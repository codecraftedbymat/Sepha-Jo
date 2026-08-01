# =====================================================================
#  Sepha-Jo — image de déploiement
#
#  Ce fichier se place à la RACINE du dépôt (à côté de index.html),
#  et non dans salon/. Railway le détecte automatiquement lorsque le
#  Root Directory est vide.
#
#  Le site vitrine (index.html) est servi à la racine du domaine ;
#  l'application de réservation vit sous /salon/public/.
# =====================================================================

FROM php:8.2-apache

# Marqueur de version : le modifier invalide le cache Docker.
ENV BUILD_REV=1

# --- Dépendances ------------------------------------------------------
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html

# --- PHPMailer --------------------------------------------------------
# Les dépendances sont déclarées dans salon/composer.json.
# Tolérant à l'échec : un problème d'e-mail ne doit pas bloquer le déploiement.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN if [ -f salon/composer.json ]; then \
        cd salon && composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
        || echo "AVERTISSEMENT : Composer a echoue, les e-mails ne partiront pas." ; \
    fi

RUN chown -R www-data:www-data /var/www/html

# =====================================================================
#  Démarrage
#
#  Deux corrections doivent se faire ICI, au lancement du conteneur :
#
#  1. Les MPM. Le script apache2-foreground de l'image officielle
#     réactive mpm_event à chaque démarrage, ce qui fait échouer Apache
#     avec « More than one MPM loaded ». On nettoie donc juste avant.
#
#  2. Le port, imposé par Railway via la variable PORT et connu
#     seulement à l'exécution : Apache ne substitue pas les variables
#     d'environnement dans ses fichiers de configuration.
# =====================================================================
RUN printf '%s\n' \
    '#!/bin/sh' \
    'set -e' \
    '' \
    '# Un seul MPM : on retire les liens puis on ne remet que prefork.' \
    'rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf' \
    'ln -sf ../mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load' \
    'ln -sf ../mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf' \
    '' \
    'PORT="${PORT:-80}"' \
    'echo "Listen ${PORT}" > /etc/apache2/ports.conf' \
    'cat > /etc/apache2/sites-available/000-default.conf <<EOF' \
    '<VirtualHost *:${PORT}>' \
    '    DocumentRoot /var/www/html' \
    '    DirectoryIndex index.html index.php' \
    '' \
    '    <Directory /var/www/html>' \
    '        Options -Indexes +FollowSymLinks' \
    '        AllowOverride All' \
    '        Require all granted' \
    '    </Directory>' \
    '' \
    '    # Les dossiers internes ne doivent pas etre servis : ils' \
    '    # contiennent la configuration et la logique metier.' \
    '    <Directory /var/www/html/salon/includes>' \
    '        Require all denied' \
    '    </Directory>' \
    '    <Directory /var/www/html/salon/api>' \
    '        Require all denied' \
    '    </Directory>' \
    '    <Directory /var/www/html/salon/lib>' \
    '        Require all denied' \
    '    </Directory>' \
    '    <Directory /var/www/html/salon/vendor>' \
    '        Require all denied' \
    '    </Directory>' \
    '' \
    '    # Ni les scripts SQL, ni la documentation, ni Git.' \
    '    <FilesMatch "[.](sql|md|lock|json|yml|yaml)$">' \
    '        Require all denied' \
    '    </FilesMatch>' \
    '    <FilesMatch "^[.]">' \
    '        Require all denied' \
    '    </FilesMatch>' \
    '' \
    '    ErrorLog /proc/self/fd/2' \
    '    CustomLog /proc/self/fd/1 combined' \
    '</VirtualHost>' \
    'EOF' \
    '' \
    '# Apache ne transmet pas les variables du conteneur a PHP : on les' \
    '# declare explicitement, sinon getenv() renvoie false dans les' \
    '# scripts alors que le shell les voit bien.' \
    'VARS="MYSQLHOST MYSQLPORT MYSQLUSER MYSQLPASSWORD MYSQLDATABASE"' \
    'VARS="$VARS DATABASE_URL MYSQL_URL"' \
    'VARS="$VARS SALON_NOM SALON_EMAIL SALON_TEL SALON_ADRESSE SALON_URL TZ"' \
    'VARS="$VARS SMTP_HOST SMTP_PORT SMTP_USER SMTP_PASS SMTP_FROM"' \
    'VARS="$VARS WEBHOOK_AGENDA RAILWAY_PUBLIC_DOMAIN"' \
    ': > /etc/apache2/conf-enabled/passenv.conf' \
    'for v in $VARS; do' \
    '    echo "PassEnv $v" >> /etc/apache2/conf-enabled/passenv.conf' \
    'done' \
    '' \
    'apache2ctl configtest' \
    'echo "Apache demarre sur le port ${PORT}"' \
    '' \
    '# apache2-foreground reactiverait mpm_event : on lance donc httpd' \
    '# directement, en conservant les variables attendues par la config.' \
    'export APACHE_RUN_USER=www-data' \
    'export APACHE_RUN_GROUP=www-data' \
    'export APACHE_PID_FILE=/var/run/apache2/apache2.pid' \
    'export APACHE_RUN_DIR=/var/run/apache2' \
    'export APACHE_LOCK_DIR=/var/lock/apache2' \
    'export APACHE_LOG_DIR=/var/log/apache2' \
    'mkdir -p "$APACHE_RUN_DIR" "$APACHE_LOCK_DIR" "$APACHE_LOG_DIR"' \
    'rm -f "$APACHE_PID_FILE"' \
    'exec apache2 -DFOREGROUND' \
    > /usr/local/bin/demarrer.sh \
    && chmod +x /usr/local/bin/demarrer.sh

CMD ["/usr/local/bin/demarrer.sh"]
