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

use App\Entity\Base\AbstractDBElement;
use App\Entity\Parts\Part;
use App\Settings\MiscSettings\IpnSuggestSettings;
use App\Validator\Constraints\UniquePartIpnConstraint;
use App\Validator\Constraints\UniquePartIpnValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class UniquePartIpnValidatorTest extends ConstraintValidatorTestCase
{
    private EntityManagerInterface&MockObject $em;
    private IpnSuggestSettings&MockObject $ipnSettings;

    protected function createValidator(): ConstraintValidatorInterface
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        // createMock() bypasses the ForbidConstructorTrait; public properties are accessible directly
        $this->ipnSettings = $this->createMock(IpnSuggestSettings::class);
        $this->ipnSettings->autoAppendSuffix = false;

        return new UniquePartIpnValidator($this->em, $this->ipnSettings);
    }

    public function testNullValueIsValid(): void
    {
        $this->validator->validate(null, new UniquePartIpnConstraint());
        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->validator->validate('', new UniquePartIpnConstraint());
        $this->assertNoViolation();
    }

    public function testAutoAppendSuffixSkipsValidation(): void
    {
        $this->ipnSettings->autoAppendSuffix = true;
        $this->validator->validate('IPN-001', new UniquePartIpnConstraint());
        $this->assertNoViolation();
    }

    public function testUniqueIpnIsValid(): void
    {
        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('findBy')->willReturn([]);
        $this->em->method('getRepository')->willReturn($repo);

        $part = new Part();
        $this->setObject($part);

        $this->validator->validate('UNIQUE-IPN', new UniquePartIpnConstraint());
        $this->assertNoViolation();
    }

    public function testDuplicateIpnRaisesViolation(): void
    {
        $existingPart = new Part();
        $ref = new \ReflectionProperty(AbstractDBElement::class, 'id');
        $ref->setValue($existingPart, 99);

        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('findBy')->willReturn([$existingPart]);
        $this->em->method('getRepository')->willReturn($repo);

        // Validated part has no ID (new, unsaved part)
        $part = new Part();
        $this->setObject($part);

        $constraint = new UniquePartIpnConstraint();
        $this->validator->validate('DUPLICATE-IPN', $constraint);
        $this->buildViolation($constraint->message)
            ->setParameter('{{ value }}', 'DUPLICATE-IPN')
            ->assertRaised();
    }
}
