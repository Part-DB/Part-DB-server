<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Constraints the parent property on StructuralDBElement objects in the way, that neither the object self or any
 * of its children can be assigned.
 *
 * @Annotation
 */
class NoneOfItsChildren extends Constraint
{
    /**
     * @var string The message used if it is tried to assign a object as its own parent
     */
    public string $self_message = 'validator.noneofitschild.self';
    /**
     * @var string The message used if it is tried to use one of the children for as parent
     */
    public string $children_message = 'validator.noneofitschild.children';
}
