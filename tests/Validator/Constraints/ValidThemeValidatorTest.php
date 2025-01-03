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

use App\Validator\Constraints\ValidTheme;
use App\Validator\Constraints\ValidThemeValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ValidThemeValidatorTest extends ConstraintValidatorTestCase
{

    protected function createValidator(): ValidThemeValidator
    {
        return new ValidThemeValidator(['bootstrap', 'theme1', 'theme2']);
    }

    public function testAllowNull(): void
    {
        $this->validator->validate(null, new ValidTheme());
        $this->assertNoViolation();
    }

    public function testAllowEmpty(): void
    {
        $this->validator->validate('', new ValidTheme());
        $this->assertNoViolation();
    }

    public function testValidTheme(): void
    {
        $this->validator->validate('bootstrap', new ValidTheme());
        $this->assertNoViolation();
    }

    public function testInvalidTheme(): void
    {
        $this->validator->validate('invalid', new ValidTheme());
        $this->buildViolation('validator.selected_theme_is_invalid')
            ->setParameter('{{ value }}', 'invalid')
            ->assertRaised();
    }


}
