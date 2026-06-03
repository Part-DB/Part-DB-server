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

use App\Validator\Constraints\ValidFileFilter;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidFileFilterValidatorTest extends WebTestCase
{
    private static ValidatorInterface $validator;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::$validator = self::getContainer()->get('validator');
    }

    public function testNullIsValid(): void
    {
        $violations = self::$validator->validate(null, new ValidFileFilter());
        $this->assertCount(0, $violations);
    }

    public function testEmptyStringIsValid(): void
    {
        $violations = self::$validator->validate('', new ValidFileFilter());
        $this->assertCount(0, $violations);
    }

    public function testValidExtensionFilterIsValid(): void
    {
        $violations = self::$validator->validate('.jpg,.png', new ValidFileFilter());
        $this->assertCount(0, $violations);
    }

    public function testValidMimeTypeFilterIsValid(): void
    {
        $violations = self::$validator->validate('image/*', new ValidFileFilter());
        $this->assertCount(0, $violations);
    }

    public function testMixedValidFilterIsValid(): void
    {
        $violations = self::$validator->validate('image/*, .pdf, video/mp4', new ValidFileFilter());
        $this->assertCount(0, $violations);
    }

    public function testInvalidFilterRaisesViolation(): void
    {
        $violations = self::$validator->validate('*.notvalid', new ValidFileFilter());
        $this->assertCount(1, $violations);
    }

    public function testFullFilenameRaisesViolation(): void
    {
        $violations = self::$validator->validate('test.png', new ValidFileFilter());
        $this->assertCount(1, $violations);
    }
}
