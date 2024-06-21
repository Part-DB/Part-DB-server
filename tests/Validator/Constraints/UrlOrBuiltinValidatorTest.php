<?php

declare(strict_types=1);

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

use App\Validator\Constraints\UrlOrBuiltin;
use App\Validator\Constraints\UrlOrBuiltinValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class UrlOrBuiltinValidatorTest extends ConstraintValidatorTestCase
{

    protected function createValidator(): UrlOrBuiltinValidator
    {
        return new UrlOrBuiltinValidator();
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new UrlOrBuiltin());
        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->validator->validate('', new UrlOrBuiltin());
        $this->assertNoViolation();
    }

    public function testValidUrlIsValid(): void
    {
        $this->validator->validate('https://example.com', new UrlOrBuiltin());
        $this->assertNoViolation();
    }

    public function testValidBuiltinIsValid(): void
    {
        $this->validator->validate('%FOOTPRINTS%/test/footprint.png', new UrlOrBuiltin());
        $this->assertNoViolation();
    }

    public function testInvalidUrlIsInvalid(): void
    {
        $constraint = new UrlOrBuiltin([
            'message' => 'myMessage',
        ]);

        $this->validator->validate('invalid-url', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"invalid-url"')
            ->setCode(UrlOrBuiltin::INVALID_URL_ERROR)
            ->assertRaised();
    }
}
