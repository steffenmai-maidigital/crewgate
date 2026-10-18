# CrewGate — Laravel auf PHP/Apache, Daten in SQLite
# CrewGate — Laravel on PHP/Apache, data in SQLite
FROM php:8.4-apache

# Systempakete und PHP-Erweiterungen (LDAP, SQLite, Zip für Composer)
# System packages and PHP extensions (LDAP, SQLite, Zip for Composer)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libldap2-dev libzip-dev libicu-dev libsqlite3-dev pkg-config \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        unzip git sqlite3 \
    && docker-php-ext-configure ldap \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install ldap pdo_sqlite zip intl gd \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache-DocumentRoot auf public/ legen
# Point the Apache DocumentRoot to public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-enabled/zz-crewgate.conf

WORKDIR /var/www/html

# Erst nur composer.json kopieren, damit der Layer gecacht wird
# Copy only composer.json first so that the layer is cached
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader

# Restliche Anwendung (Leserechte unabhängig vom Dateisystem des Bauhosts)
# Rest of the application (read permissions independent of the build host's file system)
COPY . .
RUN chmod -R a+rX /var/www/html

# bootstrap/cache liegt NICHT auf einem Volume: jeder Container (auch der
# Scheduler, der als www-data läuft) braucht Schreibrechte aus dem Image
# bootstrap/cache is NOT on a volume: every container (including the
# scheduler, which runs as www-data) needs write permissions from the image
RUN chown -R www-data:www-data bootstrap/cache storage \
    && chmod -R ug+rwX bootstrap/cache storage
RUN composer dump-autoload --optimize \
    && mkdir -p /data /backups/manual public/uploads \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads /data /backups

# Versionsnummer (JJMMTTNN) als Kennzeichnung des Abbilds — erst hier,
# damit der Zwischenspeicher der Schichten davor erhalten bleibt
# Version number (YYMMDDNN) as the image label — only here,
# so that the cache of the layers before it is kept
ARG VERSION=dev
LABEL org.opencontainers.image.title="CrewGate" \
      org.opencontainers.image.version="${VERSION}" \
      org.opencontainers.image.licenses="AGPL-3.0-or-later"
# Die angezeigte Version ist immer die des Abbilds: eine gesetzte Nummer
# gewinnt gegen den Stand in config/version.php
# The displayed version is always that of the image: a set number
# wins over the value in config/version.php
RUN if [ "$VERSION" != "dev" ]; then \
        sed -ri "s/('number'\s*=>\s*)'[^']*'/\1'${VERSION}'/" config/version.php \
        && sed -ri "s/('build'\s*=>\s*)'[^']*'/\1'$(TZ=Europe/Berlin date +%y%m%d%H%M)'/" config/version.php; \
    fi

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
