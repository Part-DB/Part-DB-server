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

# first arg is `-f` or `--some-option` (taken from https://github.com/docker-library/php/blob/master/8.2/bullseye/apache/docker-php-entrypoint)
if [ "${1#-}" != "$1" ]; then
	set -- apache2-foreground "$@"
fi

# Pass to the original entrypoint
exec "$@"