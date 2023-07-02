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

use App\Entity\Attachments\AttachmentType;
use App\Validator\Constraints\Selectable;
use App\Validator\Constraints\SelectableValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class SelectableValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): SelectableValidator
    {
        return new SelectableValidator();
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new Selectable());
        $this->assertNoViolation();
    }

    public function testExpectAbstractStructuralElement(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate('test', new Selectable());
    }

    public function testWithSelectableObj(): void
    {
        $selectable_obj = new AttachmentType();
        $selectable_obj->setNotSelectable(false);

        $this->validator->validate($selectable_obj, new Selectable());
        $this->assertNoViolation();
    }

    public function testWithNotSelectableObj(): void
    {
        $selectable_obj = new AttachmentType();
        $selectable_obj->setNotSelectable(true);
        $selectable_obj->setName('Test');

        $this->validator->validate($selectable_obj, new Selectable());
        $this->buildViolation('validator.isSelectable')
            ->setParameter('{{ name }}', 'Test')
            ->setParameter('{{ full_path }}', 'Test')
            ->assertRaised();
    }

}
