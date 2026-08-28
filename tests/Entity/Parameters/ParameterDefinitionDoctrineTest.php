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
use Doctrine\ORM\PersistentCollection;
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

    public function testUsedChoiceIsPersistedAsDeprecatedWithoutRewritingPartValue(): void
    {
        $definition = (new ParameterDefinition())
            ->setName('Doctrine retired dielectric')
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X7R', 'X5R']);
        $parameter = (new PartParameter())->setDefinition($definition)->setValueText('X7R');
        $category = (new Category())->setName('Doctrine retired category');
        $part = (new Part())
            ->setName('Doctrine retired part')
            ->setCategory($category)
            ->addParameter($parameter);
        $this->entityManager->persist($definition);
        $this->entityManager->persist($category);
        $this->entityManager->persist($part);
        $this->entityManager->flush();
        $definition_id = $definition->getID();
        $parameter_id = $parameter->getID();
        self::assertNotNull($definition_id);
        self::assertNotNull($parameter_id);
        $this->entityManager->clear();

        $definition = $this->entityManager->find(ParameterDefinition::class, $definition_id);
        self::assertInstanceOf(ParameterDefinition::class, $definition);
        $definition->setChoices(['C0G', 'X5R']);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded_definition = $this->entityManager->find(ParameterDefinition::class, $definition_id);
        $reloaded_parameter = $this->entityManager->find(PartParameter::class, $parameter_id);
        self::assertInstanceOf(ParameterDefinition::class, $reloaded_definition);
        self::assertInstanceOf(PartParameter::class, $reloaded_parameter);
        self::assertSame(['C0G', 'X5R'], $reloaded_definition->getChoices());
        self::assertSame(['X7R'], $reloaded_definition->getDeprecatedChoices());
        self::assertSame('X7R', $reloaded_parameter->getValueText());
        $validator = self::getContainer()->get(ValidatorInterface::class);
        self::assertCount(0, $validator->validate($reloaded_parameter));

        // A clone is a new assignment and must not inherit permission to preserve the historical value.
        $cloned_parameter = clone $reloaded_parameter;
        $violation_paths = [];
        foreach ($validator->validate($cloned_parameter) as $violation) {
            $violation_paths[] = $violation->getPropertyPath();
        }
        self::assertContains('value_text', $violation_paths);
    }

    public function testEditingChoicesDoesNotInitializeParameterUsages(): void
    {
        $definition = (new ParameterDefinition())
            ->setName('Doctrine permanent vocabulary')
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X7R', 'X5R']);
        $category = (new Category())->setName('Doctrine permanent vocabulary category');
        $part = (new Part())
            ->setName('Doctrine permanent vocabulary part')
            ->setCategory($category)
            ->addParameter((new PartParameter())->setDefinition($definition)->setValueText('C0G'));
        $this->entityManager->persist($definition);
        $this->entityManager->persist($category);
        $this->entityManager->persist($part);
        $this->entityManager->flush();
        $definition_id = $definition->getID();
        self::assertNotNull($definition_id);
        $this->entityManager->clear();

        $definition = $this->entityManager->find(ParameterDefinition::class, $definition_id);
        self::assertInstanceOf(ParameterDefinition::class, $definition);
        $parameter_usages = $definition->getParameterUsages();
        self::assertInstanceOf(PersistentCollection::class, $parameter_usages);
        self::assertFalse($parameter_usages->isInitialized());

        $definition->setChoices(['C0G']);

        self::assertFalse($parameter_usages->isInitialized());
        self::assertSame(['X7R', 'X5R'], $definition->getDeprecatedChoices());
    }

    public function testDefinitionAssociationMetadataIsNullableAndRestrictive(): void
    {
        $metadata = $this->entityManager->getClassMetadata(AbstractParameter::class);
        $mapping = $metadata->getAssociationMapping('definition');

        self::assertTrue($mapping['joinColumns'][0]->nullable);
        self::assertSame('RESTRICT', $mapping['joinColumns'][0]->onDelete);
        self::assertSame(ParameterDefinition::class, $mapping['targetEntity']);
        self::assertContains('capturePersistedChoiceAssignment', $metadata->getLifecycleCallbacks(Events::postLoad));
        self::assertContains('capturePersistedChoiceAssignment', $metadata->getLifecycleCallbacks(Events::postPersist));
        self::assertContains('capturePersistedChoiceAssignment', $metadata->getLifecycleCallbacks(Events::postUpdate));
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
