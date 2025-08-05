#!/bin/sh
#
# This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
#
#  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
#
#  This program is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as published
#  by the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU Affero General Public License for more details.
#
#  You should have received a copy of the GNU Affero General Public License
#  along with this program.  If not, see <https://www.gnu.org/licenses/>.
#

set -e

# recursive chowns can take a while, so we'll just do it if the owner is wrong

# Chown uploads/ folder if it does not belong to www-data
if [ "$(stat -c '%u' /var/www/html/uploads)" != "$(id -u www-data)" ]; then
    chown -R www-data:www-data /var/www/html/uploads
fi

# Do the same for the public/media folder
if [ "$(stat -c '%u' /var/www/html/public/media)" != "$(id -u www-data)" ]; then
    chown -R www-data:www-data /var/www/html/public/media
fi

# If var/db/ folder exists, do the same for it
if [ -d /var/www/html/var/db ]; then
    if [ "$(stat -c '%u' /var/www/html/var/db)" != "$(id -u www-data)" ]; then
        chown -R www-data:www-data /var/www/html/var/db
    fi
fi

# Start PHP-FPM (the PHP_VERSION is replaced by the configured version in the Dockerfile)
service phpPHP_VERSION-fpm start


# Run migrations if automigration is enabled via env variable DB_AUTOMIGRATE
if [ "$DB_AUTOMIGRATE" = "true" ]; then
		echo "Waiting for database to be ready..."
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(sudo -E -u www-data php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo "The database is not up or not reachable:"
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo "The database is now ready and reachable"
		fi

    # Check if there are any available migrations to do, by executing doctrine:migrations:up-to-date
    # and checking if the exit code is 0 (up to date) or 1 (not up to date)
    if  sudo -E -u www-data php bin/console doctrine:migrations:up-to-date --no-interaction; then
        echo "Database is up to date, no migrations necessary."
    else
        echo "Migrations available..."
        echo "Do backup of database..."

        sudo -E -u www-data mkdir -p /var/www/html/uploads/.automigration-backup/
        # Backup the database
        sudo -E -u www-data php bin/console partdb:backup -n --database /var/www/html/uploads/.automigration-backup/backup-$(date +%Y-%m-%d_%H-%M-%S).zip

        # Check if there are any migration files
        sudo -E -u www-data php bin/console doctrine:migrations:migrate --no-interaction
    fi

fi

# first arg is `-f` or `--some-option` (taken from https://github.com/docker-library/php/blob/master/8.2/bullseye/apache/docker-php-entrypoint)
if [ "${1#-}" != "$1" ]; then
	set -- apache2-foreground "$@"
fi

# Pass to the original entrypoint
exec "$@"