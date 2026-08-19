<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Services\LogSystem;

use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\Parameters\ParameterDefinition;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Services\LogSystem\TimeTravel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TimeTravelTest extends KernelTestCase
{

    private TimeTravel $service;
    private EntityManagerInterface $em;

    public function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(TimeTravel::class);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testUndeleteEntity(): void
    {
        $undeletedCategory = $this->service->undeleteEntity(Category::class, 100);

        $this->assertInstanceOf(Category::class, $undeletedCategory);
        $this->assertSame(100, $undeletedCategory->getId());
    }

    public function testApplyEntry(): void
    {
        $category = new Category();
        //Fake an ID
        $reflClass = new \ReflectionClass($category);
        $reflClass->getProperty('id')->setValue($category, 1000);

        $category->setName('Test Category');
        $category->setComment('Test Comment');

        $logEntry = new ElementEditedLogEntry($category);
        $logEntry->setOldData(['name' => 'Old Category', 'comment' => 'Old Comment']);

        $this->service->applyEntry($category, $logEntry);

        $this->assertSame('Old Category', $category->getName());
        $this->assertSame('Old Comment', $category->getComment());
    }

    public function testRevertEntityToTimestamp(): void
    {
        /** @var Category $category */
        $category = $this->em->find(Category::class, 1);

        $this->service->revertEntityToTimestamp($category, new \DateTime('2022-01-01 00:00:00'));

        //The category with 1 should have the name 'Test' at this timestamp
        $this->assertEquals('Test', $category->getName());
    }

    public function testApplyingHistoricalParameterDataRestoresVisibleSnapshotsButKeepsCurrentEffectiveDefinition(): void
    {
        $definition = (new ParameterDefinition())
            ->setName('Dielectric type')
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X7R', 'X5R']);
        $parameter = (new PartParameter())->setDefinition($definition);
        (new \ReflectionClass($parameter))->getProperty('id')->setValue($parameter, 1001);

        $log_entry = new ElementEditedLogEntry($parameter);
        $log_entry->setOldData([
            'name' => 'Dielectric',
        ]);

        $this->service->applyEntry($parameter, $log_entry);

        self::assertSame('Dielectric', $parameter->getSnapshotName());
        self::assertSame('Dielectric type', $parameter->getEffectiveName());
        self::assertSame(ParameterDefinition::INPUT_TYPE_CHOICE, $parameter->getEffectiveInputType());
        self::assertSame(['C0G', 'X7R', 'X5R'], $parameter->getEffectiveChoices());
    }

    public function testApplyingHistoricalParameterDataRestoresNullDefinitionReference(): void
    {
        $definition = (new ParameterDefinition())->setName('Current definition');
        $parameter = (new PartParameter())->setDefinition($definition);
        (new \ReflectionClass($parameter))->getProperty('id')->setValue($parameter, 1002);

        $log_entry = new ElementEditedLogEntry($parameter);
        $log_entry->setOldData([
            'definition' => null,
            'name' => 'Historical ad hoc parameter',
        ]);

        $this->service->applyEntry($parameter, $log_entry);

        self::assertNull($parameter->getDefinition());
        self::assertFalse($definition->getParameterUsages()->contains($parameter));
        self::assertSame('Historical ad hoc parameter', $parameter->getSnapshotName());
        self::assertSame('Historical ad hoc parameter', $parameter->getEffectiveName());
    }

    public function testApplyingHistoricalParameterDataRestoresPreviousDefinitionReference(): void
    {
        $previous_definition = (new ParameterDefinition())->setName('Previous definition');
        $current_definition = (new ParameterDefinition())->setName('Current replacement definition');
        $this->em->persist($previous_definition);
        $this->em->persist($current_definition);
        $this->em->flush();

        $parameter = (new PartParameter())->setDefinition($current_definition);
        (new \ReflectionClass($parameter))->getProperty('id')->setValue($parameter, 1003);
        $log_entry = new ElementEditedLogEntry($parameter);
        $log_entry->setOldData([
            'definition' => ['@id' => $previous_definition->getID()],
            'name' => 'Historical snapshot name',
        ]);

        $this->service->applyEntry($parameter, $log_entry);

        self::assertSame($previous_definition, $parameter->getDefinition());
        self::assertTrue($previous_definition->getParameterUsages()->contains($parameter));
        self::assertFalse($current_definition->getParameterUsages()->contains($parameter));
        self::assertSame('Historical snapshot name', $parameter->getSnapshotName());
        self::assertSame('Previous definition', $parameter->getEffectiveName());
    }

    public function testMissingHistoricalDefinitionFallsBackToSnapshots(): void
    {
        $deleted_definition = (new ParameterDefinition())->setName('Deleted historical definition');
        $this->em->persist($deleted_definition);
        $this->em->flush();
        $deleted_definition_id = $deleted_definition->getID();
        self::assertNotNull($deleted_definition_id);
        $this->em->remove($deleted_definition);
        $this->em->flush();

        $parameter = (new PartParameter())
            ->setName('Current snapshot')
            ->setSymbol('S')
            ->setUnit('V');
        (new \ReflectionClass($parameter))->getProperty('id')->setValue($parameter, 1004);

        $log_entry = new ElementEditedLogEntry($parameter);
        $log_entry->setOldData([
            'definition' => ['@id' => $deleted_definition_id],
            'name' => 'Historical dielectric',
            'symbol' => 'D',
            'unit' => 'grade',
        ]);

        $this->service->applyEntry($parameter, $log_entry);

        self::assertNull($parameter->getDefinition());
        self::assertSame('Historical dielectric', $parameter->getEffectiveName());
        self::assertSame('D', $parameter->getEffectiveSymbol());
        self::assertSame('grade', $parameter->getEffectiveUnit());
        self::assertSame(ParameterDefinition::INPUT_TYPE_TEXT, $parameter->getEffectiveInputType());
        self::assertSame([], $parameter->getEffectiveChoices());
    }
}
