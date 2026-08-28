<?php

declare(strict_types=1);

namespace App\Tests\Entity\Parameters;

use App\Entity\Parameters\ParameterDefinition;
use App\Entity\Parameters\PartParameter;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ParameterDefinitionTest extends TestCase
{
    public function testChoicesAreCanonicalizedCaseInsensitively(): void
    {
        $definition = (new ParameterDefinition())
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoicesText(" X7R \r\nx7r\n\nX7r\n X5R \n");

        self::assertSame(['X7R', 'X5R'], $definition->getChoices());
        self::assertSame("X7R\nX5R", $definition->getChoicesText());
    }

    public function testAddingAChoiceReusesTheExistingCanonicalSpelling(): void
    {
        $definition = (new ParameterDefinition())
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['X7R']);

        self::assertSame('X7R', $definition->addChoice(' x7r '));
        self::assertSame(['X7R'], $definition->getChoices());
        self::assertSame('X5R', $definition->addChoice(' X5R '));
        self::assertSame(['X7R', 'X5R'], $definition->getChoices());
    }

    /** CHOICE-DEPRECATION-001 */
    public function testRemovingUsedChoiceDeprecatesItWithoutChangingParameterValue(): void
    {
        $definition = (new ParameterDefinition())
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X7R', 'X5R']);
        $parameter = (new PartParameter())->setDefinition($definition)->setValueText('X7R');

        $definition->setChoices(['C0G', 'X5R']);

        self::assertSame(['C0G', 'X5R'], $definition->getChoices());
        self::assertSame(['X7R'], $definition->getDeprecatedChoices());
        self::assertSame('X7R', $parameter->getValueText());
    }

    /** CHOICE-DEPRECATION-002 */
    public function testRemovingChoiceUsedByMultipleParametersDeprecatesItOnlyOnce(): void
    {
        $definition = (new ParameterDefinition())
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X7R', 'X5R']);
        $first = (new PartParameter())->setDefinition($definition)->setValueText('X7R');
        $second = (new PartParameter())->setDefinition($definition)->setValueText('x7r');

        $definition->setChoices(['C0G', 'X5R']);

        self::assertSame(['X7R'], $definition->getDeprecatedChoices());
        self::assertSame('X7R', $first->getValueText());
        self::assertSame('X7R', $second->getValueText());
    }

    /** CHOICE-DEPRECATION-003 */
    public function testRemovingUnusedChoiceMovesItToPermanentDeprecatedVocabulary(): void
    {
        $definition = (new ParameterDefinition())
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X7R', 'X5R']);

        $definition->setChoices(['C0G', 'X5R']);

        self::assertSame(['C0G', 'X5R'], $definition->getChoices());
        self::assertSame(['X7R'], $definition->getDeprecatedChoices());
    }

    public function testRemovingSeveralChoicesPreservesExistingDeprecatedVocabularyWithoutDuplicates(): void
    {
        $definition = (new ParameterDefinition())
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X7R', 'X5R'])
            ->setDeprecatedChoices(['Y5V']);

        $definition->setChoices(['C0G']);
        $definition->setChoices(['C0G']);

        self::assertSame(['C0G'], $definition->getChoices());
        self::assertSame(['Y5V', 'X7R', 'X5R'], $definition->getDeprecatedChoices());
    }

    /** CHOICE-DEPRECATION-004 */
    public function testReactivatingDeprecatedChoiceIsCaseInsensitiveAndPreservesCanonicalSpelling(): void
    {
        $definition = (new ParameterDefinition())
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X5R'])
            ->setDeprecatedChoices([' X7R ', 'x7r', '', 'Y5V']);

        $definition->setChoices(['C0G', 'X5R', 'x7r']);

        self::assertSame(['C0G', 'X5R', 'X7R'], $definition->getChoices());
        self::assertSame(['Y5V'], $definition->getDeprecatedChoices());
        self::assertSame('X7R', $definition->findCanonicalKnownChoice(' x7r '));
    }

    /** CHOICE-DEPRECATION-011 */
    public function testActiveAndDeprecatedChoicesRemainCanonicalAndDisjoint(): void
    {
        $definition = (new ParameterDefinition())
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X5R', ''])
            ->setDeprecatedChoices([' x7r ', 'X7R', '', 'x5r']);

        self::assertSame(['C0G', 'X5R'], $definition->getChoices());
        self::assertSame(['x7r'], $definition->getDeprecatedChoices());
        self::assertNotContains('', $definition->getKnownChoices());
    }

    public function testTextDefinitionsDoNotKeepChoices(): void
    {
        $definition = (new ParameterDefinition())
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['X7R'])
            ->setDeprecatedChoices(['Y5V'])
            ->setInputType(ParameterDefinition::INPUT_TYPE_TEXT);

        self::assertSame([], $definition->getChoices());
        self::assertSame([], $definition->getDeprecatedChoices());
        $this->expectException(LogicException::class);
        $definition->addChoice('X5R');
    }

    public function testUnsupportedInputTypeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ParameterDefinition())->setInputType('unsupported');
    }

    public function testOverlongChoiceIsCanonicalizedWithoutDomainException(): void
    {
        $choice = str_repeat('X', ParameterDefinition::MAX_CHOICE_LENGTH + 1);
        $definition = (new ParameterDefinition())
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices([$choice]);

        self::assertSame([$choice], $definition->getChoices());
    }

    public function testNameIsTrimmedAndNormalized(): void
    {
        $definition = (new ParameterDefinition())->setName('  DiElEcTrIc  ');

        self::assertSame('DiElEcTrIc', $definition->getName());
        self::assertSame('dielectric', $definition->getNormalizedName());
    }

    public function testSnapshotAndEffectiveMetadataStayExplicitlySeparated(): void
    {
        $definition = (new ParameterDefinition())
            ->setName('Dielectric')
            ->setSymbol('D')
            ->setUnit('legacy-unit')
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X7R']);
        $parameter = (new PartParameter())->setDefinition($definition)->setValueText('x7r');

        self::assertSame('Dielectric', $parameter->getName());
        self::assertSame('Dielectric', $parameter->getSnapshotName());
        self::assertSame(['C0G', 'X7R'], $parameter->getEffectiveChoices());
        self::assertSame('X7R', $parameter->getValueText());

        $definition
            ->setName('Dielectric type')
            ->setSymbol('DT')
            ->setUnit('current-unit')
            ->setChoices(['C0G', 'X7R', 'X5R']);

        self::assertSame('Dielectric', $parameter->getSnapshotName());
        self::assertSame('D', $parameter->getSnapshotSymbol());
        self::assertSame('legacy-unit', $parameter->getSnapshotUnit());
        self::assertSame('Dielectric type', $parameter->getEffectiveName());
        self::assertSame('DT', $parameter->getEffectiveSymbol());
        self::assertSame('current-unit', $parameter->getEffectiveUnit());
        self::assertSame(ParameterDefinition::INPUT_TYPE_CHOICE, $parameter->getEffectiveInputType());
        self::assertSame(['C0G', 'X7R', 'X5R'], $parameter->getEffectiveChoices());
    }

    public function testLegacyParameterWithoutDefinitionUsesFreeTextMetadata(): void
    {
        $parameter = (new PartParameter())
            ->setName('Legacy')
            ->setSymbol('L')
            ->setUnit('V')
            ->setValueText('free-form value');

        self::assertNull($parameter->getDefinition());
        self::assertSame($parameter->getSnapshotName(), $parameter->getEffectiveName());
        self::assertSame($parameter->getSnapshotSymbol(), $parameter->getEffectiveSymbol());
        self::assertSame($parameter->getSnapshotUnit(), $parameter->getEffectiveUnit());
        self::assertSame(ParameterDefinition::INPUT_TYPE_TEXT, $parameter->getEffectiveInputType());
        self::assertSame([], $parameter->getEffectiveChoices());
        self::assertSame('free-form value', $parameter->getValueText());
    }

    public function testDefinitionRelationIsSynchronizedInMemory(): void
    {
        $first_definition = (new ParameterDefinition())->setName('First definition');
        $second_definition = (new ParameterDefinition())->setName('Second definition');
        $parameter = new PartParameter();

        $parameter->setDefinition($first_definition);
        self::assertTrue($first_definition->getParameterUsages()->contains($parameter));

        $parameter->setDefinition($second_definition);
        self::assertFalse($first_definition->getParameterUsages()->contains($parameter));
        self::assertTrue($second_definition->getParameterUsages()->contains($parameter));

        $parameter->setDefinition(null);
        self::assertFalse($second_definition->getParameterUsages()->contains($parameter));
        self::assertNull($parameter->getDefinition());
    }
}
