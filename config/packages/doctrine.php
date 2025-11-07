<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

/**
 * This class extends the default doctrine ORM configuration to enable native lazy objects on PHP 8.4+.
 * We have to do this in a PHP file, because the yaml file does not support conditionals on PHP version.
 */

return static function(\Symfony\Config\DoctrineConfig $doctrine) {
    //On PHP 8.4+ we can use native lazy objects, which are much more efficient than proxies.
    if (PHP_VERSION_ID >= 80400) {
        $doctrine->orm()->enableNativeLazyObjects(true);
    }
};
