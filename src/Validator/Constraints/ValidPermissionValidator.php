<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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
 *
 */

namespace App\Validator\Constraints;


use App\Entity\Parts\PartLot;
use App\Entity\UserSystem\PermissionsEmbed;
use App\Security\Interfaces\HasPermissionsInterface;
use App\Services\PermissionResolver;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidPermissionValidator extends ConstraintValidator
{

    protected $resolver;
    protected $perm_structure;

    public function __construct(PermissionResolver $resolver)
    {
        $this->resolver = $resolver;
        $this->perm_structure = $resolver->getPermissionStructure();
    }

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed $value The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     */
    public function validate($value, Constraint $constraint)
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
                    $this->resolver->dontInherit($perm_holder, $perm_key, $op_key) === true) {
                    //Set every op listed in also Set
                    foreach ($op['alsoSet'] as $set_also) {
                        $this->resolver->setPermission($perm_holder, $perm_key, $set_also, true);
                    }
                }
            }
        }
    }
}