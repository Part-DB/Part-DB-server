<?php

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

use App\Entity\UserSystem\User;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ValidGoogleAuthCodeValidator extends ConstraintValidator
{
    protected $googleAuthenticator;

    public function __construct(GoogleAuthenticator $googleAuthenticator)
    {
        $this->googleAuthenticator = $googleAuthenticator;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (! $constraint instanceof ValidGoogleAuthCode) {
            throw new UnexpectedTypeException($constraint, ValidGoogleAuthCode::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (! \is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (! ctype_digit($value)) {
            $this->context->addViolation('validator.google_code.only_digits_allowed');
        }

        //Number must have 6 digits
        if (6 !== \strlen($value)) {
            $this->context->addViolation('validator.google_code.wrong_digit_count');
        }

        //Try to retrieve the user we want to check
        if ($this->context->getObject() instanceof FormInterface &&
            $this->context->getObject()->getParent() instanceof FormInterface
        && $this->context->getObject()->getParent()->getData() instanceof User) {
            $user = $this->context->getObject()->getParent()->getData();

            //Check if the given code is valid
            if (! $this->googleAuthenticator->checkCode($user, $value)) {
                $this->context->addViolation('validator.google_code.wrong_code');
            }
        }
    }
}
