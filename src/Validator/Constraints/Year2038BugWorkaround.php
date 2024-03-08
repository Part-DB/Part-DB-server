<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Datetime interfaces properties with this constraint are limited to the year 2038 on 32-bit systems, to prevent a
 * Year 2038 bug during rendering.
 *
 * Current PHP versions can not format dates after 2038 on 32-bit systems and throw an exception.
 * (See https://github.com/Part-DB/Part-DB-server/discussions/548).
 *
 * This constraint does not fix that problem, but can prevent users from entering such invalid dates.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Year2038BugWorkaround extends Constraint
{
    public string $message = 'validator.year_2038_bug_on_32bit';
}