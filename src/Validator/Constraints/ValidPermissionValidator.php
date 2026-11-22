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

use App\Controller\AdminPages\AdminPageController;
use App\Controller\UserController;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Security\Interfaces\HasPermissionsInterface;
use App\Services\UserSystem\PermissionManager;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

use function Symfony\Component\Translation\t;

class ValidPermissionValidator extends ConstraintValidator
{
    public function __construct(protected PermissionManager $resolver, protected RequestStack $requestStack)
    {
    }

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidPermission) {
            throw new UnexpectedTypeException($constraint, ValidPermission::class);
        }

        /** @var HasPermissionsInterface $perm_holder */
        $perm_holder = $this->context->getObject();

        $changed = $this->resolver->ensureCorrectSetOperations($perm_holder);

        //Sending a flash message if the permissions were fixed (only if called from a User/Group admin page)
        //This is pretty hacky and bad design but I dont see a better way without a complete rewrite of how permissions are validated
        //on the admin pages
        if ($changed) {
            $request = $this->requestStack->getMainRequest();
            if ($request === null) {
                return;
            }
            //Determine the controller class (the part before the ::)
            $controller_class = explode('::', (string) $request->attributes->get('_controller'))[0];
            $entity_class = $request->attributes->get('entityClass', '');

            $isUserAdminPage = $controller_class === AdminPageController::class
                && in_array($entity_class, [User::class, Group::class], true);
            $isLegacyUserController = $controller_class === UserController::class;

            if ($isUserAdminPage || $isLegacyUserController) {
                /** @var Session $session */
                $session = $this->requestStack->getSession();
                $flashBag = $session->getFlashBag();
                $flashBag->add('warning', t('user.edit.flash.permissions_fixed'));
            }
        }
    }
}
