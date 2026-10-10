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

use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\Parameters\ParameterDefinition;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Repository\LogEntryRepository;
use App\Services\LogSystem\TimeTravel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

    /** CHOICE-DEPRECATION-021 */
    public function testHistoricalPartRevertRestoresDeprecatedChoiceWithoutReactivatingIt(): void
    {
        $definition = (new ParameterDefinition())
            ->setName('TimeTravel dielectric')
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['X7R', 'X5R']);
        $category = (new Category())->setName('TimeTravel deprecated category');
        $target_parameter = (new PartParameter())->setDefinition($definition)->setValueText('X7R');
        $target_part = (new Part())
            ->setName('TimeTravel target part')
            ->setCategory($category)
            ->addParameter($target_parameter);
        $this->em->persist($definition);
        $this->em->persist($category);
        $this->em->persist($target_part);
        $this->em->flush();
        $definition_id = $definition->getID();
        $part_id = $target_part->getID();
        $parameter_id = $target_parameter->getID();
        self::assertNotNull($definition_id);
        self::assertNotNull($part_id);
        self::assertNotNull($parameter_id);

        $log_repository = $this->em->getRepository(AbstractLogEntry::class);
        self::assertInstanceOf(LogEntryRepository::class, $log_repository);
        $creation_entries = array_values(array_filter(
            $log_repository->getElementHistory($target_parameter),
            static fn (AbstractLogEntry $entry): bool => $entry instanceof ElementCreatedLogEntry,
        ));
        self::assertCount(1, $creation_entries);
        $creation_entries[0]->setTimestamp(new \DateTimeImmutable('2026-01-01 00:00:00'));
        $this->em->flush();

        // T0 genuinely exists in the database.
        $this->em->clear();
        $target_parameter = $this->em->find(PartParameter::class, $parameter_id);
        self::assertInstanceOf(PartParameter::class, $target_parameter);
        self::assertSame('X7R', $target_parameter->getValueText());
        self::assertSame($definition_id, $target_parameter->getDefinition()?->getID());

        // T1 genuinely persists X5R and lets the production logger create the historical X7R revision.
        $target_parameter->setValueText('X5R');
        $this->em->flush();
        $this->em->clear();
        $target_parameter = $this->em->find(PartParameter::class, $parameter_id);
        self::assertInstanceOf(PartParameter::class, $target_parameter);
        self::assertSame('X5R', $target_parameter->getValueText());

        $log_repository = $this->em->getRepository(AbstractLogEntry::class);
        self::assertInstanceOf(LogEntryRepository::class, $log_repository);
        $historical_entries = array_values(array_filter(
            $log_repository->getElementHistory($target_parameter),
            static fn (AbstractLogEntry $entry): bool => $entry instanceof ElementEditedLogEntry
                && 'X7R' === ($entry->getOldData()['value_text'] ?? null),
        ));
        self::assertCount(1, $historical_entries);
        $historical_entry = $historical_entries[0];
        self::assertInstanceOf(ElementEditedLogEntry::class, $historical_entry);
        $historical_entry->setTimestamp(new \DateTimeImmutable('2026-01-02 00:00:00'));
        $this->em->flush();

        // T2 retires X7R without changing either current parameter value.
        $definition = $this->em->find(ParameterDefinition::class, $definition_id);
        self::assertInstanceOf(ParameterDefinition::class, $definition);
        $definition->setChoices(['X5R']);
        $this->em->flush();
        $this->em->clear();
        $definition = $this->em->find(ParameterDefinition::class, $definition_id);
        $target_part = $this->em->find(Part::class, $part_id);
        self::assertInstanceOf(ParameterDefinition::class, $definition);
        self::assertInstanceOf(Part::class, $target_part);
        self::assertSame(['X5R'], $definition->getChoices());
        self::assertSame(['X7R'], $definition->getDeprecatedChoices());

        // T3 follows the production whole-Part TimeTravel path, then persists the effective revert as LogController does.
        $this->service->revertEntityToTimestamp(
            $target_part,
            new \DateTimeImmutable('2026-01-01 12:00:00'),
        );
        $this->em->flush();

        $restored_parameter = null;
        foreach ($target_part->getParameters() as $parameter) {
            if ($parameter->getID() === $parameter_id) {
                $restored_parameter = $parameter;
                break;
            }
        }
        self::assertInstanceOf(PartParameter::class, $restored_parameter);
        self::assertSame('X7R', $restored_parameter->getValueText());
        self::assertSame($definition_id, $restored_parameter->getDefinition()?->getID());
        self::assertSame(['X5R'], $definition->getChoices());
        self::assertSame(['X7R'], $definition->getDeprecatedChoices());
        self::assertNotContains('X7R', $definition->getChoices());
        self::assertCount(0, self::getContainer()->get(ValidatorInterface::class)->validate($restored_parameter));
    }
}
