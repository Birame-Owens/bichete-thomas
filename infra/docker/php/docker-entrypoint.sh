#!/bin/sh
# Point d'entrée du conteneur PHP-FPM en production.
#
# On génère les caches Laravel AVANT de démarrer FPM : ainsi le premier
# utilisateur ne subit pas le coût de la compilation des routes/config.
# Les caches sont écrits dans le volume bootstrap/cache qui est propre
# à ce conteneur (pas de conflit inter-répliques).

set -e

# Les volumes nommes (app_storage, app_logs) sont montes par-dessus les
# dossiers de l'image et demarrent en root : le chown fait au build est
# ecrase. On le refait ici, au runtime en root, AVANT de passer FPM en
# www-data. Sans ca : logs muets (root:root) et uploads d'images en echec.
echo "[entrypoint] Ajustement des droits storage..."
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "[entrypoint] Génération des caches Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
echo "[entrypoint] Caches générés. Démarrage PHP-FPM."

# Remplace ce processus shell par php-fpm pour que Docker
# transmette bien SIGTERM/SIGQUIT au bon processus.
exec php-fpm
