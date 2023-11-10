<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Validator\Constraints;

use App\Entity\UserSystem\User;
use App\Validator\Constraints\ValidGoogleAuthCode;
use App\Validator\Constraints\ValidGoogleAuthCodeValidator;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ValidGoogleAuthCodeValidatorTest extends ConstraintValidatorTestCase
{

    protected function createValidator(): ConstraintValidatorInterface
    {
        $googleAuth = new class implements GoogleAuthenticatorInterface
        {

            public function checkCode(TwoFactorInterface $user, string $code): bool
            {
                return $code === '123456';
            }

            public function getQRContent(TwoFactorInterface $user): string
            {
                return 'not_needed';
            }

            public function generateSecret(): string
            {
                return 'not_needed';
            }
        };

        $security = new class extends Security {
            public function __construct()
            {
                //Leave empty
            }
            public function getUser(): ?\Symfony\Component\Security\Core\User\UserInterface
            {
                return new class implements TwoFactorInterface, UserInterface {

                    public function isGoogleAuthenticatorEnabled(): bool
                    {
                        return true;
                    }

                    public function getGoogleAuthenticatorUsername(): string
                    {
                        return "test";
                    }

                    public function getGoogleAuthenticatorSecret(): ?string
                    {
                        return "not_needed";
                    }

                    public function getRoles(): array
                    {
                        return [];
                    }

                    public function eraseCredentials()
                    {
                    }

                    public function getUserIdentifier(): string
                    {
                        return 'test';
                    }
                };
            }
        };


        return new ValidGoogleAuthCodeValidator($googleAuth, $security);
    }

    public function testAllowNull(): void
    {
        $this->validator->validate(null, new ValidGoogleAuthCode());
        $this->assertNoViolation();
    }

    public function testAllowEmpty(): void
    {
        $this->validator->validate('', new ValidGoogleAuthCode());
        $this->assertNoViolation();
    }

    public function testValidCode(): void
    {
        $this->validator->validate('123456', new ValidGoogleAuthCode());
        $this->assertNoViolation();
    }

    public function testInvalidCode(): void
    {
        $this->validator->validate('111111', new ValidGoogleAuthCode());
        $this->buildViolation('validator.google_code.wrong_code')
            ->assertRaised();
    }

    public function testCheckNumerical(): void
    {
        $this->validator->validate('123456a', new ValidGoogleAuthCode());
        $this->buildViolation('validator.google_code.only_digits_allowed')
            ->assertRaised();
    }

    public function testCheckLength(): void
    {
        $this->validator->validate('12345', new ValidGoogleAuthCode());
        $this->buildViolation('validator.google_code.wrong_digit_count')
            ->assertRaised();
    }
}
