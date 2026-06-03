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

use App\Validator\Constraints\Year2038BugWorkaround;
use App\Validator\Constraints\Year2038BugWorkaroundValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class Year2038BugWorkaroundValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        // Disable validation by default so tests run on both 32- and 64-bit systems
        return new Year2038BugWorkaroundValidator(disable_validation: true);
    }

    public function testIsNotActivatedWhenDisabled(): void
    {
        $validator = new Year2038BugWorkaroundValidator(disable_validation: true);
        $this->assertFalse($validator->isActivated());
    }

    public function testIsNotActivatedOn64Bit(): void
    {
        // On any normal 64-bit CI/dev system PHP_INT_SIZE === 8, so activation requires 32-bit
        if (PHP_INT_SIZE !== 8) {
            $this->markTestSkipped('This test is only meaningful on 64-bit systems.');
        }
        $validator = new Year2038BugWorkaroundValidator(disable_validation: false);
        $this->assertFalse($validator->isActivated());
    }

    public function testNullValueProducesNoViolation(): void
    {
        $this->validator->validate(null, new Year2038BugWorkaround());
        $this->assertNoViolation();
    }

    public function testDateBefore2038ProducesNoViolationWhenDisabled(): void
    {
        $this->validator->validate(new \DateTime('2037-01-01'), new Year2038BugWorkaround());
        $this->assertNoViolation();
    }

    public function testDateAfter2038ProducesNoViolationWhenDisabled(): void
    {
        // Validation disabled → even a "bad" date causes no violation
        $this->validator->validate(new \DateTime('2039-01-01'), new Year2038BugWorkaround());
        $this->assertNoViolation();
    }
}
