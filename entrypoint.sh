#!/bin/bash
set -e

# El volumen de datos se monta DESPUÉS de construir la imagen, así que el
# chown del Dockerfile no le llega. Lo hacemos aquí, en cada arranque del
# contenedor, cuando el volumen ya está disponible.
mkdir -p /var/www/data/media/clips /var/www/data/media/assets /var/www/data/media/output /var/www/data/media/tmp
chown -R www-data:www-data /var/www/data
chmod -R 775 /var/www/data

exec apache2-foreground
