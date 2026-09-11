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

namespace App\Tests\Validator\Constraints\BigDecimal;

use App\Validator\Constraints\BigDecimal\BigDecimalGreaterThanValidator;
use App\Validator\Constraints\BigDecimal\BigDecimalPositive;
use Brick\Math\BigDecimal;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Tests BigDecimalGreaterThanValidator via the BigDecimalPositive constraint (value > 0).
 */
final class BigDecimalGreaterThanValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new BigDecimalGreaterThanValidator();
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new BigDecimalPositive());
        $this->assertNoViolation();
    }

    public function testPositiveIntegerIsValid(): void
    {
        $this->validator->validate(1, new BigDecimalPositive());
        $this->assertNoViolation();
    }

    public function testPositiveStringIsValid(): void
    {
        $this->validator->validate('0.01', new BigDecimalPositive());
        $this->assertNoViolation();
    }

    public function testPositiveBigDecimalIsValid(): void
    {
        $this->validator->validate(BigDecimal::of('1.5'), new BigDecimalPositive());
        $this->assertNoViolation();
    }

    public function testZeroIsInvalid(): void
    {
        $constraint = new BigDecimalPositive();
        $this->validator->validate(0, $constraint);
        $this->buildViolation($constraint->message)
            ->setParameters(['{{ value }}' => '0', '{{ compared_value }}' => '0', '{{ compared_value_type }}' => 'int'])
            ->setCode(\Symfony\Component\Validator\Constraints\GreaterThan::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public function testZeroBigDecimalIsInvalid(): void
    {
        $constraint = new BigDecimalPositive();
        $this->validator->validate(BigDecimal::of('0.00'), $constraint);
        $this->buildViolation($constraint->message)
            ->setParameters(['{{ value }}' => '0.00', '{{ compared_value }}' => '0', '{{ compared_value_type }}' => 'int'])
            ->setCode(\Symfony\Component\Validator\Constraints\GreaterThan::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public function testNegativeIsInvalid(): void
    {
        $constraint = new BigDecimalPositive();
        $this->validator->validate(-1, $constraint);
        $this->buildViolation($constraint->message)
            ->setParameters(['{{ value }}' => '-1', '{{ compared_value }}' => '0', '{{ compared_value_type }}' => 'int'])
            ->setCode(\Symfony\Component\Validator\Constraints\GreaterThan::TOO_LOW_ERROR)
            ->assertRaised();
    }
}
