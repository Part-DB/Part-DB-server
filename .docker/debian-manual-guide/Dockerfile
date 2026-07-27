# Reproduces https://docs.part-db.de/installation/installation_guide-debian.html
# on Debian 13 (trixie) as closely as possible, for debugging issues reported
# by users who followed that guide (Apache2 + mod_php, not the project's own
# FrankenPHP-based Dockerfile at the repo root).
FROM debian:13

ARG HOST_UID=1000
ARG HOST_GID=1000

ENV DEBIAN_FRONTEND=noninteractive

# --- Prerequisites (guide: "Prerequisites Installation") ---
RUN apt-get update && apt-get upgrade -y && \
    apt-get install -y --no-install-recommends \
        git curl zip ca-certificates software-properties-common \
        apt-transport-https lsb-release nano wget sqlite3 gnupg openssl \
    && rm -rf /var/lib/apt/lists/*

# --- PHP and Apache2 (guide: "PHP and Apache2 Setup") ---
# Debian 13 ships PHP 8.4 by default, so no extra PHP repo is needed.
RUN apt-get update && apt-get install -y --no-install-recommends \
        apache2 php8.4 libapache2-mod-php8.4 \
        php8.4-opcache php8.4-curl php8.4-gd php8.4-mbstring \
        php8.4-xml php8.4-bcmath php8.4-intl php8.4-zip php8.4-xsl \
        php8.4-sqlite3 php8.4-mysql \
    && rm -rf /var/lib/apt/lists/*

# --- Composer (guide: "Composer Installation") ---
RUN wget -O /tmp/composer-setup.php https://getcomposer.org/installer \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && chmod +x /usr/local/bin/composer \
    && rm /tmp/composer-setup.php

# --- Node.js and Yarn (guide: "Node.js and Yarn Setup") ---
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && curl -sL https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor -o /usr/share/keyrings/yarnkey.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/yarnkey.gpg] https://dl.yarnpkg.com/debian stable main" > /etc/apt/sources.list.d/yarn.list \
    && apt-get update && apt-get install -y --no-install-recommends yarn \
    && rm -rf /var/lib/apt/lists/*

# Map www-data to the host user's uid/gid so files created inside the
# bind-mounted repo (composer/yarn artifacts, chown'd dirs) don't end up
# owned by a foreign uid on the host.
RUN groupmod -g ${HOST_GID} www-data && usermod -u ${HOST_UID} -g ${HOST_GID} www-data \
    && mkdir -p /var/www/partdb && chown -R www-data:www-data /var/www

# --- Apache2 Configuration (guide: "Apache2 Configuration") ---
COPY partdb.conf /etc/apache2/sites-available/partdb.conf
RUN ln -sf /etc/apache2/sites-available/partdb.conf /etc/apache2/sites-enabled/partdb.conf \
    && a2enmod rewrite headers access_compat \
    && rm -f /etc/apache2/sites-enabled/000-default.conf

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/partdb
EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2ctl", "-D", "FOREGROUND"]
