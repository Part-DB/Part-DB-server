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

use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Services\UserSystem\PermissionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NoLockoutValidator extends ConstraintValidator
{
    protected array $perm_structure;

    public function __construct(protected PermissionManager $resolver, protected \Symfony\Bundle\SecurityBundle\Security $security, protected EntityManagerInterface $entityManager)
    {
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
        if (!$constraint instanceof NoLockout) {
            throw new UnexpectedTypeException($constraint, NoLockout::class);
        }

        $perm_holder = $value;

        //Prevent that a user revokes its own change_permission perm (prevent the user to lock out itself)
        if ($perm_holder instanceof User || $perm_holder instanceof Group) {
            $user = $this->security->getUser();

            if (!$user instanceof \Symfony\Component\Security\Core\User\UserInterface) {
                $user = $this->entityManager->getRepository(User::class)->getAnonymousUser();
            }

            //Check if the change_permission permission has changed from allow to disallow
            if (($user instanceof User) && !($this->resolver->inherit(
                        $user,
                        'users',
                        'edit_permissions'
                    ) ?? false)) {
                $this->context->addViolation($constraint->message);
            }
        }
    }
}
