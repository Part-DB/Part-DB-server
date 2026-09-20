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

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parameters\CategoryParameter;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Validator\Constraints\UniqueEntityIgnoringOrphans;
use App\Validator\Constraints\UniqueEntityIgnoringOrphansValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Covers the UniqueEntityIgnoringOrphans constraint: an entity that was just taken out of its owner's
 * collection (to be replaced by a new one with the same unique value in the same request) must not be
 * reported as a conflict, even though its row has not been deleted from the database yet.
 *
 * The cases below use different owner/child pairs (Part/PartParameter, Category/CategoryParameter and
 * Part/PartAttachment) on purpose, to demonstrate that the constraint is generic and works purely from
 * Doctrine's association metadata, without requiring any interface or helper method on the entities involved.
 */
final class UniqueEntityIgnoringOrphansValidatorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->validator = $container->get(ValidatorInterface::class);
    }

    public function testRemovedPartParameterCanBeReplacedWithSameNameBeforeFlush(): void
    {
        $category = (new Category())->setName('UniqueEntityIgnoringOrphansValidatorTest Category');
        $part = (new Part())->setName('UniqueEntityIgnoringOrphansValidatorTest Part')->setCategory($category);

        $old = (new PartParameter())->setName('Voltage')->setGroup('Electrical');
        $part->addParameter($old);

        $this->em->persist($category);
        $this->em->persist($part);
        $this->em->flush();

        $oldId = $old->getID();

        // Remove the old parameter and add a new one with the same unique fields in the same request,
        // without an intermediate flush - orphan removal for $old is only queued, not yet executed.
        $part->removeParameter($old);
        $new = (new PartParameter())->setName('Voltage')->setGroup('Electrical');
        $part->addParameter($new);

        self::assertCount(0, $this->validator->validate($new), 'A replacement parameter with the same unique value as a just-removed one must be valid.');

        $this->em->flush();

        self::assertNull($this->em->find(PartParameter::class, $oldId));
        self::assertNotNull($new->getID());
    }

    public function testTwoActivePartParametersWithSameNameAreStillRejected(): void
    {
        $category = (new Category())->setName('UniqueEntityIgnoringOrphansValidatorTest Category 2');
        $part = (new Part())->setName('UniqueEntityIgnoringOrphansValidatorTest Part 2')->setCategory($category);

        $existing = (new PartParameter())->setName('Voltage')->setGroup('Electrical');
        $part->addParameter($existing);

        $this->em->persist($category);
        $this->em->persist($part);
        $this->em->flush();

        // A second, still-active parameter with the same unique value must remain a conflict.
        $duplicate = (new PartParameter())->setName('Voltage')->setGroup('Electrical');
        $part->addParameter($duplicate);

        self::assertGreaterThan(0, \count($this->validator->validate($duplicate)));
    }

    public function testRemovedCategoryParameterCanBeReplacedWithSameNameBeforeFlush(): void
    {
        $category = (new Category())->setName('UniqueEntityIgnoringOrphansValidatorTest Category 3');

        $old = (new CategoryParameter())->setName('Voltage')->setGroup('Electrical');
        $category->addParameter($old);

        $this->em->persist($category);
        $this->em->flush();

        $oldId = $old->getID();

        $category->removeParameter($old);
        $new = (new CategoryParameter())->setName('Voltage')->setGroup('Electrical');
        $category->addParameter($new);

        self::assertCount(0, $this->validator->validate($new));

        $this->em->flush();

        self::assertNull($this->em->find(CategoryParameter::class, $oldId));
        self::assertNotNull($new->getID());
    }

    public function testRemovedPartAttachmentCanBeReplacedWithSameNameBeforeFlush(): void
    {
        $category = (new Category())->setName('UniqueEntityIgnoringOrphansValidatorTest Category 4');
        $part = (new Part())->setName('UniqueEntityIgnoringOrphansValidatorTest Part 4')->setCategory($category);
        $attachmentType = (new AttachmentType())->setName('UniqueEntityIgnoringOrphansValidatorTest AttachmentType');

        $old = (new PartAttachment())->setName('Datasheet')->setAttachmentType($attachmentType)->setURL('https://example.invalid/old.pdf');
        $part->addAttachment($old);

        $this->em->persist($category);
        $this->em->persist($attachmentType);
        $this->em->persist($part);
        $this->em->flush();

        $oldId = $old->getID();

        $part->removeAttachment($old);
        $new = (new PartAttachment())->setName('Datasheet')->setAttachmentType($attachmentType)->setURL('https://example.invalid/new.pdf');
        $part->addAttachment($new);

        self::assertCount(0, $this->validator->validate($new));

        $this->em->flush();

        self::assertNull($this->em->find(PartAttachment::class, $oldId));
        self::assertNotNull($new->getID());
    }

    public function testOwnerFieldWithoutOrphanRemovalIsRejected(): void
    {
        // Category::$children (the inverse side of Category::$parent) is not mapped with orphanRemoval: true,
        // so applying the constraint there would silently ignore matches that Doctrine will never actually
        // delete - the constraint must refuse this configuration instead of allowing duplicates to persist.
        $parent = (new Category())->setName('UniqueEntityIgnoringOrphansValidatorTest Parent Category');
        $category = (new Category())->setName('UniqueEntityIgnoringOrphansValidatorTest Child Category')->setParent($parent);

        $constraint = new UniqueEntityIgnoringOrphans(fields: ['name', 'parent'], ownerField: 'parent');
        $validator = new UniqueEntityIgnoringOrphansValidator($this->em);

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('does not have orphanRemoval enabled');
        $validator->validate($category, $constraint);
    }
}
