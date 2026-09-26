FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg fonts-dejavu-core \
    && rm -rf /var/lib/apt/lists/*
COPY . /var/www/html/
COPY php-uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
# La carpeta de datos vive FUERA del docroot (/var/www/html) a propósito:
# así nadie puede descargar data.json (llaves API en texto plano) abriendo
# la URL directamente, sin depender de reglas de Apache/.htaccess.
RUN mkdir -p /var/www/data /var/www/data/media/clips /var/www/data/media/assets /var/www/data/media/output /var/www/data/media/tmp
RUN chown -R www-data:www-data /var/www/html /var/www/data && chmod -R 775 /var/www/html /var/www/data
RUN chmod +x /var/www/html/entrypoint.sh
EXPOSE 80
ENTRYPOINT ["/var/www/html/entrypoint.sh"]
