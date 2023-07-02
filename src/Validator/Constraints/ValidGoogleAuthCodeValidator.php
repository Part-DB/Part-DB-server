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

use App\Entity\UserSystem\User;
use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticator;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

use function is_string;
use function strlen;

class ValidGoogleAuthCodeValidator extends ConstraintValidator
{
    public function __construct(private GoogleAuthenticatorInterface $googleAuthenticator, private Security $security)
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidGoogleAuthCode) {
            throw new UnexpectedTypeException($constraint, ValidGoogleAuthCode::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (!ctype_digit($value)) {
            $this->context->addViolation('validator.google_code.only_digits_allowed');
            return;
        }

        //Number must have 6 digits
        if (6 !== strlen($value)) {
            $this->context->addViolation('validator.google_code.wrong_digit_count');
            return;
        }

        //Use the current user to check the code
        $user = $constraint->user ?? $this->security->getUser();
        if (!$user instanceof TwoFactorInterface) {
            throw new UnexpectedValueException($user, TwoFactorInterface::class);
        }

        //Check if the given code is valid
        if (!$this->googleAuthenticator->checkCode($user, $value)) {
            $this->context->addViolation('validator.google_code.wrong_code');
        }
    }
}
