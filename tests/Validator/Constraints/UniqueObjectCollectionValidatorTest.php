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

use App\Tests\Validator\DummyUniqueValidatableObject;
use App\Validator\Constraints\UniqueObjectCollection;
use App\Validator\Constraints\UniqueObjectCollectionValidator;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class UniqueObjectCollectionValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): UniqueObjectCollectionValidator
    {
        return new UniqueObjectCollectionValidator();
    }

    public function testEmptyCollection(): void
    {
        $this->validator->validate(new ArrayCollection([]), new UniqueObjectCollection());
        $this->assertNoViolation();
    }

    public function testUnqiueCollectionDefaultSettings(): void
    {
        $this->validator->validate(new ArrayCollection([
            new DummyUniqueValidatableObject(['a' => 1, 'b' => 1]),
            new DummyUniqueValidatableObject(['a' => 2, 'b' => 1])
        ]),
            new UniqueObjectCollection());

        $this->assertNoViolation();
    }

    public function testUnqiueCollectionSpecifiedFields(): void
    {
        $this->validator->validate(new ArrayCollection([
            new DummyUniqueValidatableObject(['a' => 1, 'b' => 1]),
            new DummyUniqueValidatableObject(['a' => 2, 'b' => 1])
        ]),
            new UniqueObjectCollection(fields: ['a']));

        $this->assertNoViolation();
    }

    public function testExpectsIterableElement(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate('string', new UniqueObjectCollection());
    }

    public function testExpectsUniqueValidatableObject(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new ArrayCollection([new \stdClass()]), new UniqueObjectCollection());
    }

    public function testNonUniqueCollectionDefaultSettings(): void
    {
        $this->validator->validate(new ArrayCollection([
            new DummyUniqueValidatableObject(['a' => 1, 'b' => 1]),
            new DummyUniqueValidatableObject(['a' => 1, 'b' => 1])
        ]),
            new UniqueObjectCollection());

        $this
            ->buildViolation('This value is already used.')
            ->setCode(UniqueObjectCollection::IS_NOT_UNIQUE)
            ->setParameter('{{ object }}', 'objectString')
            ->atPath('property.path[1].a')
            ->assertRaised();
    }

    public function testNonUniqueCollectionSpecifyFields(): void
    {
        $this->validator->validate(new ArrayCollection([
            new DummyUniqueValidatableObject(['a' => 1, 'b' => 1]),
            new DummyUniqueValidatableObject(['a' => 1, 'b' => 1])
        ]),
            new UniqueObjectCollection(fields: ['b']));

        $this
            ->buildViolation('This value is already used.')
            ->setCode(UniqueObjectCollection::IS_NOT_UNIQUE)
            ->setParameter('{{ object }}', 'objectString')
            ->atPath('property.path[1].b')
            ->assertRaised();
    }

    public function testNonUniqueCollectionFirstFieldIsTarget(): void
    {
        $this->validator->validate(new ArrayCollection([
            new DummyUniqueValidatableObject(['a' => 1, 'b' => 1]),
            new DummyUniqueValidatableObject(['a' => 1, 'b' => 1])
        ]),
            new UniqueObjectCollection(fields: ['b', 'a']));

        $this
            ->buildViolation('This value is already used.')
            ->setCode(UniqueObjectCollection::IS_NOT_UNIQUE)
            ->setParameter('{{ object }}', 'objectString')
            ->atPath('property.path[1].b')
            ->assertRaised();
    }

    public function testNonUniqueCollectionAllowNull(): void
    {
        $this->validator->validate(new ArrayCollection([
            new DummyUniqueValidatableObject(['a' => 1, 'b' => null]),
            new DummyUniqueValidatableObject(['a' => 2, 'b' => 2]),
            new DummyUniqueValidatableObject(['a' => 3, 'b' => null])
        ]),
            new UniqueObjectCollection(fields: ['b'], allowNull: true));

        $this->assertNoViolation();
    }

    public function testNonUniqueCollectionDoNotAllowNull(): void
    {
        $this->validator->validate(new ArrayCollection([
            new DummyUniqueValidatableObject(['a' => 1, 'b' => null]),
            new DummyUniqueValidatableObject(['a' => 2, 'b' => 2]),
            new DummyUniqueValidatableObject(['a' => 3, 'b' => null])
        ]),
            new UniqueObjectCollection(fields: ['b'], allowNull: false));

        $this
            ->buildViolation('This value is already used.')
            ->setCode(UniqueObjectCollection::IS_NOT_UNIQUE)
            ->setParameter('{{ object }}', 'objectString')
            ->atPath('property.path[2].b')
            ->assertRaised();
    }



}
