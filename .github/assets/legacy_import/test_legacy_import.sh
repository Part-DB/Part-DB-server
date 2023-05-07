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

#!/bin/bash

# This script is used to test the legacy import of Part-DB

SQL_FILES_TO_TEST=("db_minimal.sql" "db_jbtronics.sql")

DB_NAME="test"
DB_USER="root"
DB_PASSWORD="root"

# Iterate over all given SQL files and import them into the mysql database with the given name, drop the database if it already exists before
for SQL_FILE in "${SQL_FILES_TO_TEST[@]}"
do
    echo "Testing for $SQL_FILE"
    mysql -u $DB_USER --password=$DB_PASSWORD -e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME;"
    # If the last command failed, exit the script
    if [ $? -ne 0 ]; then
        echo "Failed to create database $DB_NAME"
        exit 1
    fi
    # Import the SQL file into the database. The file pathes are relative to the current script location
    mysql -u $DB_USER --password=$DB_PASSWORD $DB_NAME < .github/assets/legacy_import/$SQL_FILE
    # If the last command failed, exit the script
    if [ $? -ne 0 ]; then
        echo "Failed to import $SQL_FILE into database $DB_NAME"
        exit 1
    fi
    # Run doctrine migrations, this will migrate the database to the current version. This process should not fail
    php bin/console doctrine:migrations:migrate -n
    # If the last command failed, exit the script
    if [ $? -ne 0 ]; then
        echo "Failed to migrate database $DB_NAME"
        exit 1
    fi
done