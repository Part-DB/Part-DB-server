<?php

declare(strict_types=1);

namespace App\Tests\Entity\Parameters;

use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parameters\ParameterDefinition;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Group('DB')]
#[Group('slow')]
final class ParameterDefinitionDoctrineTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testDefinitionRelationAndVisibleSnapshotsArePersisted(): void
    {
        $definition = (new ParameterDefinition())
            ->setName('Doctrine dielectric')
            ->setSymbol('D')
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X7R']);
        $parameter = (new PartParameter())->setDefinition($definition)->setValueText('X7R');
        $category = (new Category())->setName('Definition relation category');
        $part = (new Part())
            ->setName('Definition relation part')
            ->setCategory($category)
            ->addParameter($parameter);

        $this->entityManager->persist($definition);
        $this->entityManager->persist($category);
        $this->entityManager->persist($part);
        $this->entityManager->flush();
        $part_id = $part->getID();
        $definition_id = $definition->getID();
        self::assertNotNull($part_id);
        self::assertNotNull($definition_id);

        $this->entityManager->clear();
        $reloaded_part = $this->entityManager->find(Part::class, $part_id);
        self::assertInstanceOf(Part::class, $reloaded_part);
        $reloaded_parameter = $reloaded_part->getParameters()->first();
        self::assertInstanceOf(PartParameter::class, $reloaded_parameter);
        self::assertSame($definition_id, $reloaded_parameter->getDefinition()?->getID());
        self::assertSame('Doctrine dielectric', $reloaded_parameter->getSnapshotName());
        self::assertSame(['C0G', 'X7R'], $reloaded_parameter->getEffectiveChoices());

        $reloaded_definition = $reloaded_parameter->getDefinition();
        self::assertInstanceOf(ParameterDefinition::class, $reloaded_definition);
        $reloaded_definition->setName('Doctrine dielectric type')->setChoices(['C0G', 'X7R', 'X5R']);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded_part = $this->entityManager->find(Part::class, $part_id);
        self::assertInstanceOf(Part::class, $reloaded_part);
        $reloaded_parameter = $reloaded_part->getParameters()->first();
        self::assertInstanceOf(PartParameter::class, $reloaded_parameter);
        self::assertSame('Doctrine dielectric', $reloaded_parameter->getSnapshotName());
        self::assertSame('Doctrine dielectric type', $reloaded_parameter->getEffectiveName());
        self::assertSame(['C0G', 'X7R', 'X5R'], $reloaded_parameter->getEffectiveChoices());
    }

    public function testLinkedChoiceValueIsValidatedAgainstTheDefinition(): void
    {
        $definition = (new ParameterDefinition())
            ->setName('Validated dielectric')
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X7R']);
        $parameter = (new PartParameter())->setDefinition($definition)->setValueText('Y5V');

        $validator = self::getContainer()->get(ValidatorInterface::class);
        $violations = $validator->validate($parameter);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('value_text', $violations[0]->getPropertyPath());
    }

    public function testDefinitionAssociationMetadataIsNullableAndRestrictive(): void
    {
        $metadata = $this->entityManager->getClassMetadata(AbstractParameter::class);
        $mapping = $metadata->getAssociationMapping('definition');

        self::assertTrue($mapping['joinColumns'][0]->nullable);
        self::assertSame('RESTRICT', $mapping['joinColumns'][0]->onDelete);
        self::assertSame(ParameterDefinition::class, $mapping['targetEntity']);
    }

    public function testNormalizedNameLifecycleCallbackRunsAfterDirectNameRestoration(): void
    {
        $metadata = $this->entityManager->getClassMetadata(ParameterDefinition::class);
        self::assertContains('updateNormalizedName', $metadata->getLifecycleCallbacks(Events::prePersist));
        self::assertContains('updateNormalizedName', $metadata->getLifecycleCallbacks(Events::preUpdate));

        $definition = (new ParameterDefinition())->setName('Lifecycle original name');
        $this->entityManager->persist($definition);
        $this->entityManager->flush();

        (new \ReflectionClass($definition))->getProperty('name')->setValue($definition, 'Lifecycle Restored Name');
        $this->entityManager->flush();
        $definition_id = $definition->getID();
        self::assertNotNull($definition_id);

        $this->entityManager->clear();
        $reloaded_definition = $this->entityManager->find(ParameterDefinition::class, $definition_id);
        self::assertInstanceOf(ParameterDefinition::class, $reloaded_definition);
        self::assertSame('Lifecycle Restored Name', $reloaded_definition->getName());
        self::assertSame('lifecycle restored name', $reloaded_definition->getNormalizedName());
    }

    public function testCompleteDoctrineMappingIsValid(): void
    {
        $errors = (new SchemaValidator($this->entityManager))->validateMapping();

        self::assertSame([], $errors, var_export($errors, true));
    }
}
