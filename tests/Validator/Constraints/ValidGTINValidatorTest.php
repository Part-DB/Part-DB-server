<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

use App\Validator\Constraints\ValidGTINValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ValidGTINValidatorTest extends ConstraintValidatorTestCase
{

    public function testAllowNull(): void
    {
        $this->validator->validate(null, new \App\Validator\Constraints\ValidGTIN());
        $this->assertNoViolation();
    }

    public function testValidGTIN8(): void
    {
        $this->validator->validate('12345670', new \App\Validator\Constraints\ValidGTIN());
        $this->assertNoViolation();
    }

    public function testValidGTIN12(): void
    {
        $this->validator->validate('123456789012', new \App\Validator\Constraints\ValidGTIN());
        $this->assertNoViolation();
    }

    public function testValidGTIN13(): void
    {
        $this->validator->validate('1234567890128', new \App\Validator\Constraints\ValidGTIN());
        $this->assertNoViolation();
    }

    public function testValidGTIN14(): void
    {
        $this->validator->validate('12345678901231', new \App\Validator\Constraints\ValidGTIN());
        $this->assertNoViolation();
    }

    public function testInvalidGTIN(): void
    {
        $this->validator->validate('1234567890123', new \App\Validator\Constraints\ValidGTIN());
        $this->buildViolation('validator.invalid_gtin')
            ->assertRaised();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ValidGTINValidator();
    }
}
