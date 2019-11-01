<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony)
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
 *
 */

namespace App\Validator\Constraints;


use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Services\PermissionResolver;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NoLockoutValidator extends ConstraintValidator
{

    protected $resolver;
    protected $perm_structure;
    protected $security;
    protected $entityManager;

    public function __construct(PermissionResolver $resolver, Security $security, EntityManagerInterface $entityManager)
    {
        $this->resolver = $resolver;
        $this->perm_structure = $resolver->getPermissionStructure();
        $this->security = $security;
        $this->entityManager = $entityManager;
    }

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed $value The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof NoLockout) {
            throw new UnexpectedTypeException($constraint, NoLockout::class);
        }


        $perm_holder = $value;

        //Prevent that a user revokes its own change_permission perm (prevent the user to lock out itself)
        if ($perm_holder instanceof User || $perm_holder instanceof Group) {

            $user = $this->security->getUser();

            if ($user === null) {
                $user = $this->entityManager->getRepository(User::class)->getAnonymousUser();
            }

            if ($user instanceof User) {
                //Check if we the change_permission permission has changed from allow to disallow
                if (($this->resolver->inherit($user, 'users', 'edit_permissions') ?? false) === false) {
                    $this->context->addViolation($constraint->message);
                }
            }
        }
    }
}