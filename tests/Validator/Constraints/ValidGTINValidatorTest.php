<?php

declare(strict_types=1);

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

use App\Validator\Constraints\ValidGTIN;
use App\Validator\Constraints\ValidGTINValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class ValidGTINValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ValidGTINValidator();
    }

    // --- values that must produce no violation ---

    public static function validValuesProvider(): \Generator
    {
        yield 'null is skipped'         => [null];
        yield 'empty string is skipped' => [''];
        yield 'valid GTIN-8'            => ['12345670'];
        yield 'valid GTIN-12'           => ['123456789012'];
        yield 'valid GTIN-13'           => ['1234567890128'];
        yield 'valid GTIN-14'           => ['12345678901231'];
    }

    #[DataProvider('validValuesProvider')]
    public function testValidValue(mixed $value): void
    {
        $this->validator->validate($value, new ValidGTIN());
        $this->assertNoViolation();
    }

    // --- values that must produce a violation ---

    public static function invalidValuesProvider(): \Generator
    {
        yield 'wrong check digit (GTIN-13)' => ['1234567890123'];
        yield 'non-numeric string'          => ['ABCDEFGHIJKLM'];
        yield 'wrong length — 9 digits'     => ['123456789'];
        yield 'wrong length — 11 digits'    => ['12345678901'];
        yield 'leading whitespace'          => [' 1234567890128'];
        yield 'trailing whitespace'         => ['1234567890128 '];
    }

    #[DataProvider('invalidValuesProvider')]
    public function testInvalidValue(string $value): void
    {
        $this->validator->validate($value, new ValidGTIN());
        $this->buildViolation('validator.invalid_gtin')
            ->assertRaised();
    }
}
