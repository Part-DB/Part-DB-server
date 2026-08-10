#!/bin/sh
set -eu

APP_DIR="/var/www/html"
DEV_HOME="/var/www/partdb-dev-home"

prepare_runtime()
{
    # Development-only: newly created files are writable by all users,
    # and newly created directories are writable/traversable by all users.
    umask 000

    mkdir -p \
        "$DEV_HOME" \
        "$DEV_HOME/.composer" \
        "$DEV_HOME/.cache/composer" \
        "$DEV_HOME/.cache/yarn" \
        "$APP_DIR/vendor" \
        "$APP_DIR/node_modules" \
        "$APP_DIR/var/cache" \
        "$APP_DIR/var/log" \
        "$APP_DIR/var/db" \
        "$APP_DIR/var/share" \
        "$APP_DIR/uploads" \
        "$APP_DIR/public/media" \
        "$APP_DIR/public/build" \
        "$APP_DIR/public/bundles"

    chown -R www-data:www-data "$DEV_HOME"

    # Development-only permission workaround for Linux bind mounts.
    chmod 777 \
        "$APP_DIR/vendor" \
        "$APP_DIR/node_modules" \
        "$APP_DIR/var/cache" \
        "$APP_DIR/var/log" \
        "$APP_DIR/var/db" \
        "$APP_DIR/var/share" \
        "$APP_DIR/uploads" \
        "$APP_DIR/public/media" \
        "$APP_DIR/public/build" \
        "$APP_DIR/public/bundles"

    export HOME="$DEV_HOME"
    export COMPOSER_HOME="$DEV_HOME/.composer"
    export COMPOSER_CACHE_DIR="$DEV_HOME/.cache/composer"
    export YARN_CACHE_FOLDER="$DEV_HOME/.cache/yarn"

    mkdir -p \
        "$DEV_HOME" \
        "$DEV_HOME/.composer" \
        "$DEV_HOME/.cache/composer" \
        "$DEV_HOME/.cache/yarn"

    chown -R www-data:www-data "$DEV_HOME"

    git config --system --get-all safe.directory 2>/dev/null \
        | grep -Fxq "$APP_DIR" \
        || git config --system --add safe.directory "$APP_DIR"
}

start_server()
{
    "php-fpm${PHP_VERSION}" -F &
    exec /usr/local/bin/apache2-foreground
}

prepare_runtime

case "${1:-}" in
    apache2-foreground|/usr/local/bin/apache2-foreground)
        start_server
        ;;

    *)
        exec gosu www-data "$@"
        ;;
esac
