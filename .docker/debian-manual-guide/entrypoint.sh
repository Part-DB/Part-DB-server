#!/bin/bash
# Reproduces the app-setup steps from
# https://docs.part-db.de/installation/installation_guide-debian.html
# against the repo bind-mounted at /var/www/partdb.
set -euo pipefail

cd /var/www/partdb

# Only chown the named-volume mount points (see docker-compose.yml) - they're
# fresh docker volumes owned by root until first use. The bind-mounted source
# tree doesn't need it (www-data's uid/gid is mapped to the host user's at
# image build time, so ownership already matches), and recursing into it
# would also try (and fail) to chown the read-only .env.local mount.
chown -R www-data:www-data \
    var vendor node_modules public/build public/media uploads

as_www() {
    # -p preserves the environment (APP_ENV/APP_SECRET/DATABASE_URL from
    # docker-compose) across the user switch. Plain `su` resets it, which
    # would silently fall back to whatever's baked into .env.local.
    su -p -s /bin/bash www-data -c "$*"
}

if [ ! -f vendor/autoload.php ] || [ "${FORCE_REINSTALL:-0}" = "1" ]; then
    echo "==> composer install -o"
    as_www "composer install -o"
fi

if [ ! -d node_modules/.bin ] || [ "${FORCE_REINSTALL:-0}" = "1" ]; then
    echo "==> yarn install"
    as_www "yarn install"
fi

if [ ! -f public/build/manifest.json ] || [ "${FORCE_REINSTALL:-0}" = "1" ]; then
    echo "==> yarn build"
    as_www "yarn build"
fi

echo "==> cache:clear"
as_www "php bin/console cache:clear"

echo "==> partdb:check-requirements"
as_www "php bin/console partdb:check-requirements" || true

echo "==> doctrine:migrations:migrate"
as_www "php bin/console doctrine:migrations:migrate --no-interaction"

exec "$@"
