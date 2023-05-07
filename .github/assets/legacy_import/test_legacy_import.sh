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

# Iterate over all given SQL files and import them into the mysql database with the given name, drop the database if it already exists before
for SQL_FILE in "${SQL_FILES_TO_TEST[@]}"
do
    echo "Testing for $SQL_FILE"
    mysql -u root -e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME;"
    # Import the SQL file into the database. The file pathes are relative to the current script location
    mysql -u root $DB_NAME < .github/assets/legacy_import/$SQL_FILE
    # Run doctrine migrations, this will migrate the database to the current version. This process should not fail
    php bin/console doctrine:migrations:migrate -n
done