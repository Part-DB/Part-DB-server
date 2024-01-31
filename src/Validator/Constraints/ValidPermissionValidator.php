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

use App\Controller\GroupController;
use App\Controller\UserController;
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
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidPermission) {
            throw new UnexpectedTypeException($constraint, ValidPermission::class);
        }

        /** @var HasPermissionsInterface $perm_holder */
        $perm_holder = $this->context->getObject();

        $changed = $this->resolver->ensureCorrectSetOperations($perm_holder);

        //Sending a flash message if the permissions were fixed (only if called from UserController or GroupController)
        //This is pretty hacky and bad design but I dont see a better way without a complete rewrite of how permissions are validated
        //on the admin pages
        if ($changed) {
            //Check if this was called in context of UserController
            $request = $this->requestStack->getMainRequest();
            if (!$request) {
                return;
            }
            //Determine the controller class (the part before the ::)
            $controller_class = explode('::', $request->attributes->get('_controller'))[0];

            if (in_array($controller_class, [UserController::class, GroupController::class], true)) {
                /** @var Session $session */
                $session = $this->requestStack->getSession();
                $flashBag = $session->getFlashBag();
                $flashBag->add('warning', t('user.edit.flash.permissions_fixed'));
            }
        }
    }
}
