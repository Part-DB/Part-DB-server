<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Validator\Constraints;

use App\Security\Interfaces\HasPermissionsInterface;
use App\Services\PermissionResolver;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidPermissionValidator extends ConstraintValidator
{
    protected PermissionResolver $resolver;
    protected array $perm_structure;

    public function __construct(PermissionResolver $resolver)
    {
        $this->resolver = $resolver;
        $this->perm_structure = $resolver->getPermissionStructure();
    }

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidPermission) {
            throw new UnexpectedTypeException($constraint, ValidPermission::class);
        }

        /** @var HasPermissionsInterface $perm_holder */
        $perm_holder = $this->context->getObject();

        //Check for each permission and operation, for an alsoSet attribute
        foreach ($this->perm_structure['perms'] as $perm_key => $permission) {
            foreach ($permission['operations'] as $op_key => $op) {
                if (!empty($op['alsoSet']) &&
                    true === $this->resolver->dontInherit($perm_holder, $perm_key, $op_key)) {
                    //Set every op listed in also Set
                    foreach ($op['alsoSet'] as $set_also) {
                        $this->resolver->setPermission($perm_holder, $perm_key, $set_also, true);
                    }
                }
            }
        }
    }
}
