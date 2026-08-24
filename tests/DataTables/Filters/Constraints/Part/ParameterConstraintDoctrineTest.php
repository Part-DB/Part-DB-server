<?php

declare(strict_types=1);

namespace App\Tests\DataTables\Filters\Constraints\Part;

use App\DataTables\Filters\Constraints\Part\ParameterConstraint;
use App\Entity\Parameters\ParameterDefinition;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('DB')]
#[Group('slow')]
final class ParameterConstraintDoctrineTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testLinkedDefinitionMatchesOnlyTheRequestedChoice(): void
    {
        $definition = $this->createChoiceDefinition('Query linked dielectric', ['X7R', 'X5R']);
        $matching_part = $this->createPart(
            'Query linked matching part',
            (new PartParameter())->setDefinition($definition)->setValueText('X7R'),
        );
        $wrong_part = $this->createPart(
            'Query linked wrong part',
            (new PartParameter())->setDefinition($definition)->setValueText('X5R'),
        );
        $this->entityManager()->flush();

        $constraint = $this->choiceConstraint($definition, 'X7R');

        self::assertSame(
            [$matching_part->getID()],
            $this->findMatchingPartIds([$constraint], [$matching_part, $wrong_part]),
        );
    }

    public function testChoiceDefinitionWithNonEqualityOperatorIsInactive(): void
    {
        $definition = $this->createChoiceDefinition('Query operator dielectric', ['X7R']);
        $part_with_matching_text = $this->createPart(
            'Query operator part with matching text',
            (new PartParameter())->setDefinition($definition)->setValueText('X7R'),
        );
        $part_without_parameter = $this->createPart('Query operator part without parameter');
        $this->entityManager()->flush();

        $constraint = (new ParameterConstraint())->setDefinition($definition);
        $constraint->getValueText()->setOperator('CONTAINS')->setValue('X7R');

        self::assertFalse($constraint->isEnabled());
        self::assertSame(
            [$part_with_matching_text->getID(), $part_without_parameter->getID()],
            $this->findMatchingPartIds([$constraint], [$part_with_matching_text, $part_without_parameter]),
        );
    }

    public function testChoiceDefinitionIgnoresStaleNumericConstraint(): void
    {
        $definition = $this->createChoiceDefinition('Query numeric dielectric', ['X7R', 'X5R']);
        $matching_part = $this->createPart(
            'Query numeric matching part',
            (new PartParameter())->setDefinition($definition)->setValueText('X7R'),
        );
        $wrong_part = $this->createPart(
            'Query numeric wrong part',
            (new PartParameter())->setDefinition($definition)->setValueText('X5R'),
        );
        $this->entityManager()->flush();

        $constraint = $this->choiceConstraint($definition, 'X7R');
        $constraint->getValue()->setOperator('=');
        $constraint->getValue()->setValue1(123.0);

        self::assertTrue($constraint->getValue()->isEnabled());
        self::assertSame(
            [$matching_part->getID()],
            $this->findMatchingPartIds([$constraint], [$matching_part, $wrong_part]),
        );
    }

    public function testMultipleDefinitionsAreSatisfiedByDifferentParameterRows(): void
    {
        $dielectric = $this->createChoiceDefinition('Query multiple dielectric', ['X7R', 'X5R']);
        $package = $this->createChoiceDefinition('Query multiple package', ['0603', '0805']);
        $matching_part = $this->createPart(
            'Query multiple matching part',
            (new PartParameter())->setDefinition($dielectric)->setValueText('X7R'),
            (new PartParameter())->setDefinition($package)->setValueText('0603'),
        );
        $dielectric_only = $this->createPart(
            'Query multiple dielectric only',
            (new PartParameter())->setDefinition($dielectric)->setValueText('X7R'),
        );
        $package_only = $this->createPart(
            'Query multiple package only',
            (new PartParameter())->setDefinition($package)->setValueText('0603'),
        );
        $wrong_dielectric = $this->createPart(
            'Query multiple wrong dielectric',
            (new PartParameter())->setDefinition($dielectric)->setValueText('X5R'),
            (new PartParameter())->setDefinition($package)->setValueText('0603'),
        );
        $this->entityManager()->flush();

        self::assertCount(2, $matching_part->getParameters());
        self::assertSame(
            [$matching_part->getID()],
            $this->findMatchingPartIds(
                [
                    $this->choiceConstraint($dielectric, 'X7R'),
                    $this->choiceConstraint($package, '0603'),
                ],
                [$matching_part, $dielectric_only, $package_only, $wrong_dielectric],
            ),
        );
    }

    public function testUnlinkedLegacyParametersUseNormalizedNameAndChoiceEquality(): void
    {
        $definition = $this->createChoiceDefinition('Query legacy dielectric', ['X7R']);
        $exact_part = $this->createPart(
            'Query legacy exact part',
            $this->createAdHocParameter('Query legacy dielectric', 'X7R'),
        );
        $normalized_part = $this->createPart(
            'Query legacy normalized part',
            $this->createAdHocParameter(' query LEGACY dielectric ', ' x7r '),
        );
        $this->entityManager()->flush();

        self::assertSame(
            [$exact_part->getID(), $normalized_part->getID()],
            $this->findMatchingPartIds(
                [$this->choiceConstraint($definition, 'X7R')],
                [$exact_part, $normalized_part],
            ),
        );
    }

    public function testAnotherLinkedDefinitionNeverMatchesThroughItsSnapshotName(): void
    {
        $definition_a = $this->createChoiceDefinition('Query protected dielectric', ['X7R']);
        $definition_b = $this->createChoiceDefinition('Query protected unrelated definition', ['X7R']);
        $matching_part = $this->createPart(
            'Query protected matching part',
            (new PartParameter())->setDefinition($definition_a)->setValueText('X7R'),
        );
        $unrelated_parameter = (new PartParameter())->setDefinition($definition_b);
        $unrelated_parameter->setName($definition_a->getName());
        $unrelated_parameter->setValueText('X7R');
        $unrelated_part = $this->createPart(
            'Query protected unrelated part',
            $unrelated_parameter,
        );
        $this->entityManager()->flush();

        self::assertSame(
            [$matching_part->getID()],
            $this->findMatchingPartIds(
                [$this->choiceConstraint($definition_a, 'X7R')],
                [$matching_part, $unrelated_part],
            ),
        );
    }

    public function testLinkedParameterKeepsMatchingAfterDefinitionRename(): void
    {
        $definition = $this->createChoiceDefinition('Query rename dielectric', ['X7R']);
        $parameter = (new PartParameter())->setDefinition($definition)->setValueText('X7R');
        $part = $this->createPart('Query rename linked part', $parameter);
        $this->entityManager()->flush();
        self::assertSame('Query rename dielectric', $parameter->getSnapshotName());

        $definition->setName('Query renamed dielectric type');
        $this->entityManager()->flush();

        self::assertSame(
            [$part->getID()],
            $this->findMatchingPartIds([$this->choiceConstraint($definition, 'X7R')], [$part]),
        );
        self::assertSame('Query rename dielectric', $parameter->getSnapshotName());
    }

    public function testExistingAdHocNameSearchRemainsSupported(): void
    {
        $matching_part = $this->createPart(
            'Query ad hoc matching part',
            $this->createAdHocParameter('Query custom code', 'ABC-123'),
        );
        $wrong_part = $this->createPart(
            'Query ad hoc wrong part',
            $this->createAdHocParameter('Query other code', 'ABC-123'),
        );
        $this->entityManager()->flush();

        $constraint = (new ParameterConstraint())->setName('Query custom code');

        self::assertSame(
            [$matching_part->getID()],
            $this->findMatchingPartIds([$constraint], [$matching_part, $wrong_part]),
        );
    }

    public function testCompletelyEmptyConstraintIsInactive(): void
    {
        $part_with_parameter = $this->createPart(
            'Query empty constraint part with parameter',
            $this->createAdHocParameter('Query existing parameter'),
        );
        $part_without_parameter = $this->createPart('Query empty constraint part without parameter');
        $this->entityManager()->flush();
        $constraint = new ParameterConstraint();
        $choice_definition = $this->createChoiceDefinition('Query empty choice definition', ['X7R']);
        $empty_choice_constraint = $this->choiceConstraint($choice_definition, '');

        self::assertFalse($constraint->isEnabled());
        self::assertFalse($empty_choice_constraint->isEnabled());
        self::assertSame(
            [$part_with_parameter->getID(), $part_without_parameter->getID()],
            $this->findMatchingPartIds([$constraint], [$part_with_parameter, $part_without_parameter]),
        );
    }

    /** @param list<string> $choices */
    private function createChoiceDefinition(string $name, array $choices): ParameterDefinition
    {
        $definition = (new ParameterDefinition())
            ->setName($name)
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices($choices);
        $this->entityManager()->persist($definition);

        return $definition;
    }

    private function createPart(string $name, PartParameter ...$parameters): Part
    {
        $category = new Category();
        $category->setName($name.' category');
        $part = new Part();
        $part->setName($name);
        $part->setCategory($category);
        foreach ($parameters as $parameter) {
            $part->addParameter($parameter);
        }
        $this->entityManager()->persist($category);
        $this->entityManager()->persist($part);

        return $part;
    }

    private function createAdHocParameter(string $name, string $value = ''): PartParameter
    {
        $parameter = new PartParameter();
        $parameter->setName($name);
        $parameter->setValueText($value);

        return $parameter;
    }

    private function choiceConstraint(ParameterDefinition $definition, string $value): ParameterConstraint
    {
        $constraint = (new ParameterConstraint())->setDefinition($definition);
        $constraint->getValueText()->setOperator('=')->setValue($value);

        return $constraint;
    }

    /**
     * @param list<ParameterConstraint> $constraints
     * @param list<Part> $candidate_parts
     * @return list<int>
     */
    private function findMatchingPartIds(array $constraints, array $candidate_parts): array
    {
        $candidate_ids = array_map(static fn (Part $part): int => (int) $part->getID(), $candidate_parts);
        $query_builder = $this->entityManager()->createQueryBuilder()
            ->select('part.id')
            ->from(Part::class, 'part')
            ->where('part.id IN (:candidate_ids)')
            ->setParameter('candidate_ids', $candidate_ids)
            ->orderBy('part.id', 'ASC');

        foreach ($constraints as $constraint) {
            $constraint->apply($query_builder);
        }

        return array_map(
            static fn (array $row): int => (int) $row['id'],
            $query_builder->getQuery()->getArrayResult(),
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
