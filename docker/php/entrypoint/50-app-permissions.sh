#!/bin/sh
# ---------------------------------------------------------------------------
# Droits du répertoire var/ monté dans un volume Docker.
#
# Les volumes sont créés par le démon Docker en root ; PHP-FPM s'exécute sous
# l'utilisateur « application » (uid 1000). Sans ce script, Symfony ne peut ni
# écrire ses journaux ni ses sessions et renvoie une erreur 500.
# ---------------------------------------------------------------------------
set -u

VAR_DIR=/var/www/html/var

mkdir -p "$VAR_DIR/cache" "$VAR_DIR/log" "$VAR_DIR/sessions"

if id application >/dev/null 2>&1; then
    chown -R application:application "$VAR_DIR" 2>/dev/null || true
    chmod -R ug+rwX "$VAR_DIR" 2>/dev/null || true
fi
