# ---------------------------------------------------------------------------
# Image applicative ONE ERP Finance & Project Control
#
# Base : webdevops/php-dev:8.3 — PHP 8.3 (build officiel) livré avec Composer,
# Git, make et l'ensemble des extensions nécessaires (pdo_pgsql, intl, zip,
# bcmath, gd, opcache, sodium, xsl). Ce choix évite toute compilation
# d'extensions au démarrage du conteneur : l'environnement de développement est
# reproductible et démarre en quelques secondes.
#
# Le conteneur s'exécute en root (nécessaire au superviseur de l'image), les
# workers PHP-FPM tournent sous l'utilisateur « application » (uid/gid 1000).
# ---------------------------------------------------------------------------
FROM webdevops/php-dev:8.3

# Configuration applicative : fuseau, tailles d'upload, sessions, opcache.
# Chargée en dernier (zz-) pour primer sur la configuration de l'image.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Xdebug n'est utile que sur demande : désactivé par défaut (performance).
COPY docker/php/xdebug-off.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

# Les volumes Docker (vendor, var) sont créés en root : ce script d'entrée
# attribue var/ à l'utilisateur des workers PHP-FPM après le montage.
COPY docker/php/entrypoint/50-app-permissions.sh /opt/docker/provision/entrypoint.d/50-app-permissions.sh
RUN chmod +x /opt/docker/provision/entrypoint.d/50-app-permissions.sh

# Extensions d'observabilité de l'image de base (profiler Excimer, Tideways,
# OpenTelemetry) : inutiles ici et source de segfaults au arrêt du processus PHP
# en CLI comme en PHP-FPM. On les retire pour garantir des codes de sortie fiables
# (migrations, tests, commandes de sauvegarde).
RUN rm -f /usr/local/etc/php/conf.d/excimer.ini \
          /usr/local/etc/php/conf.d/tideways.ini \
          /usr/local/etc/php/conf.d/opentelemetry.ini

# Sentinelle de version de l'image applicative.
ENV APP_IMAGE=one-erp-finance/php \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

# php-fpm est supervisé par l'entrypoint de l'image (CMD supervisord par défaut).
