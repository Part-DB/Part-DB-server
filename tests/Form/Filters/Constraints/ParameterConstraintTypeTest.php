<?php

declare(strict_types=1);

namespace App\Tests\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\Part\ParameterConstraint;
use App\Entity\Parameters\ParameterDefinition;
use App\Form\Filters\Constraints\ParameterChoiceConstraintType;
use App\Form\Filters\Constraints\ParameterConstraintType;
use App\Form\Filters\Constraints\TextConstraintType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

#[Group('DB')]
#[Group('slow')]
final class ParameterConstraintTypeTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testChoiceDefinitionRendersOnlyItsChoicesAndAnEmptyPlaceholder(): void
    {
        $definition = $this->createDefinition(
            'Search form dielectric choices',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R', 'X5R', 'C0G'],
        );
        $constraint = (new ParameterConstraint())->setDefinition($definition);
        $constraint->getValueText()->setValue('X7R');
        $form = $this->createConstraintForm($constraint);
        $value_form = $form->get('value_text');

        self::assertInstanceOf(
            ParameterChoiceConstraintType::class,
            $value_form->getConfig()->getType()->getInnerType(),
        );
        self::assertInstanceOf(
            ChoiceType::class,
            $value_form->get('value')->getConfig()->getType()->getInnerType(),
        );
        self::assertSame(
            ['X7R' => 'X7R', 'X5R' => 'X5R', 'C0G' => 'C0G'],
            $value_form->get('value')->getConfig()->getOption('choices'),
        );
        self::assertSame(
            'selectpicker.nothing_selected',
            $value_form->get('value')->getConfig()->getOption('placeholder'),
        );
        self::assertArrayNotHasKey('', $value_form->get('value')->getConfig()->getOption('choices'));
        self::assertSame('=', $constraint->getValueText()->getOperator());
    }

    public function testChoiceSubmitUsesDefinitionIdentityAndForcesEquality(): void
    {
        $definition = $this->createDefinition(
            'Search form dielectric submit',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R', 'X5R'],
        );
        $constraint = new ParameterConstraint();
        $form = $this->createConstraintForm($constraint);

        $form->submit($this->submission($definition, 'X7R', 'CONTAINS'));

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($definition, $constraint->getDefinition());
        self::assertSame('=', $constraint->getValueText()->getOperator());
        self::assertSame('X7R', $constraint->getValueText()->getValue());
        self::assertTrue($constraint->isEnabled());
    }

    public function testGlobalDefinitionClearsSubmittedAdHocFields(): void
    {
        $definition = $this->createDefinition(
            'Search form stale cleanup',
            ParameterDefinition::INPUT_TYPE_TEXT,
        );
        $constraint = new ParameterConstraint();
        $form = $this->createConstraintForm($constraint);
        $submitted_data = $this->submission($definition, 'automotive', 'CONTAINS');
        $submitted_data['symbol'] = 'stale symbol';
        $submitted_data['unit'] = 'stale unit';
        $submitted_data['value'] = [
            'operator' => '>',
            'value1' => '10',
            'value2' => '',
        ];

        $form->submit($submitted_data);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('', $constraint->getSymbol());
        self::assertSame('', $constraint->getUnit());
        self::assertFalse($constraint->getValue()->isEnabled());
        self::assertSame('CONTAINS', $constraint->getValueText()->getOperator());
        self::assertSame('automotive', $constraint->getValueText()->getValue());
    }

    public function testGlobalDefinitionCanonicalizesSubmittedName(): void
    {
        $definition = $this->createDefinition(
            'Search form canonical name',
            ParameterDefinition::INPUT_TYPE_TEXT,
        );
        $constraint = new ParameterConstraint();
        $form = $this->createConstraintForm($constraint);
        $submitted_data = $this->submission($definition, 'automotive', 'CONTAINS');
        $submitted_data['name'] = 'Wrong name';

        $form->submit($submitted_data);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($definition, $constraint->getDefinition());
        self::assertSame($definition->getName(), $constraint->getName());
    }

    public function testRenamedDefinitionCanonicalizesOldSubmittedName(): void
    {
        $old_name = 'Search form old definition name';
        $definition = $this->createDefinition(
            $old_name,
            ParameterDefinition::INPUT_TYPE_TEXT,
        );
        $definition->setName('Search form current definition name');
        $this->entityManager()->flush();
        $constraint = new ParameterConstraint();
        $form = $this->createConstraintForm($constraint);
        $submitted_data = $this->submission($definition, 'automotive', 'CONTAINS');
        $submitted_data['name'] = $old_name;

        $form->submit($submitted_data);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($definition, $constraint->getDefinition());
        self::assertSame('Search form current definition name', $constraint->getName());
    }

    public function testEmptyChoiceIsValidAndInactive(): void
    {
        $definition = $this->createDefinition(
            'Search form dielectric empty',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R'],
        );
        $constraint = new ParameterConstraint();
        $form = $this->createConstraintForm($constraint);

        $form->submit($this->submission($definition, ''));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('', (string) $constraint->getValueText()->getValue());
        self::assertSame('=', $constraint->getValueText()->getOperator());
        self::assertFalse($constraint->isEnabled());
    }

    public function testForgedChoiceIsInvalid(): void
    {
        $definition = $this->createDefinition(
            'Search form dielectric forged',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R'],
        );
        $form = $this->createConstraintForm(new ParameterConstraint());

        $form->submit($this->submission($definition, 'X6R'));

        self::assertFalse($form->isValid());
    }

    public function testTextDefinitionKeepsTextConstraintOperatorsAndValues(): void
    {
        $definition = $this->createDefinition(
            'Search form manufacturer note',
            ParameterDefinition::INPUT_TYPE_TEXT,
        );
        $constraint = new ParameterConstraint();
        $form = $this->createConstraintForm($constraint);

        $form->submit($this->submission($definition, 'automotive', 'CONTAINS'));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($definition, $constraint->getDefinition());
        self::assertSame('CONTAINS', $constraint->getValueText()->getOperator());
        self::assertSame('automotive', $constraint->getValueText()->getValue());
        self::assertInstanceOf(
            TextConstraintType::class,
            $form->get('value_text')->getConfig()->getType()->getInnerType(),
        );
    }

    public function testAdHocModePreservesHistoricalFieldsAndConstraints(): void
    {
        $constraint = new ParameterConstraint();
        $form = $this->createConstraintForm($constraint);
        $submitted_data = $this->submission(null, 'ABC', '=');
        $submitted_data['name'] = 'CustomCode';
        $submitted_data['symbol'] = 'CC';
        $submitted_data['unit'] = 'V';
        $submitted_data['value'] = [
            'operator' => '>',
            'value1' => '12.5',
            'value2' => '',
        ];

        $form->submit($submitted_data);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertNull($constraint->getDefinition());
        self::assertSame('CustomCode', $constraint->getName());
        self::assertSame('CC', $constraint->getSymbol());
        self::assertSame('V', $constraint->getUnit());
        self::assertSame('ABC', $constraint->getValueText()->getValue());
        self::assertSame('=', $constraint->getValueText()->getOperator());
        self::assertSame(12.5, $constraint->getValue()->getValue1());
        self::assertSame('>', $constraint->getValue()->getOperator());
    }

    public function testInvalidDefinitionIdIsRejectedInsteadOfBecomingAdHoc(): void
    {
        $constraint = new ParameterConstraint();
        $form = $this->createConstraintForm($constraint);
        $submitted_data = $this->submission(null, 'X7R', '=');
        $submitted_data['definition'] = '2147483647';

        $form->submit($submitted_data);

        self::assertFalse($form->isValid());
        self::assertNull($constraint->getDefinition());
    }

    public function testGetRoundTripRestoresDefinitionAndChoiceValue(): void
    {
        $definition = $this->createDefinition(
            'Search form dielectric GET',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R', 'X5R'],
        );
        $query = http_build_query([
            'parameter' => $this->submission($definition, 'X7R'),
        ], '', '&', PHP_QUERY_RFC3986);

        self::assertStringContainsString(
            urlencode('parameter[definition]').'='.urlencode((string) $definition->getID()),
            $query,
        );
        self::assertStringContainsString('X7R', $query);

        foreach (range(1, 2) as $_reload) {
            $constraint = new ParameterConstraint();
            $form = $this->formFactory()->createNamed('parameter', ParameterConstraintType::class, $constraint, [
                'csrf_protection' => false,
                'method' => 'GET',
            ]);
            $form->handleRequest(Request::create('/parts?'.$query, 'GET'));

            self::assertTrue($form->isSubmitted());
            self::assertTrue($form->isValid(), (string) $form->getErrors(true));
            self::assertSame($definition, $constraint->getDefinition());
            self::assertSame('X7R', $constraint->getValueText()->getValue());
            self::assertSame('=', $constraint->getValueText()->getOperator());
        }
    }

    public function testTwoCollectionRowsRestoreIndependentChoiceDefinitions(): void
    {
        $dielectric = $this->createDefinition(
            'Search form independent dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R', 'X5R'],
        );
        $package = $this->createDefinition(
            'Search form independent package',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['0603', '0805'],
        );
        $form = $this->formFactory()->createNamedBuilder('filter', FormType::class, null, [
            'csrf_protection' => false,
            'method' => 'GET',
        ])->add('parameters', CollectionType::class, [
            'entry_type' => ParameterConstraintType::class,
            'allow_add' => true,
        ])->getForm();

        $form->submit([
            'parameters' => [
                $this->submission($dielectric, 'X7R'),
                $this->submission($package, '0603'),
            ],
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        $first = $form->get('parameters')->get('0')->getData();
        $second = $form->get('parameters')->get('1')->getData();
        self::assertInstanceOf(ParameterConstraint::class, $first);
        self::assertInstanceOf(ParameterConstraint::class, $second);
        self::assertNotSame($first, $second);
        self::assertSame($dielectric, $first->getDefinition());
        self::assertSame('X7R', $first->getValueText()->getValue());
        self::assertSame($package, $second->getDefinition());
        self::assertSame('0603', $second->getValueText()->getValue());
    }

    /** @param list<string>|null $choices */
    private function createDefinition(string $name, string $input_type, ?array $choices = null): ParameterDefinition
    {
        $definition = (new ParameterDefinition())
            ->setName($name)
            ->setInputType($input_type)
            ->setChoices($choices);
        $this->entityManager()->persist($definition);
        $this->entityManager()->flush();

        return $definition;
    }

    private function createConstraintForm(ParameterConstraint $constraint): FormInterface
    {
        return $this->formFactory()->create(ParameterConstraintType::class, $constraint, [
            'csrf_protection' => false,
        ]);
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function formFactory(): FormFactoryInterface
    {
        return static::getContainer()->get(FormFactoryInterface::class);
    }

    /** @return array<string, mixed> */
    private function submission(
        ?ParameterDefinition $definition,
        string $value,
        string $operator = '=',
    ): array {
        return [
            'definition' => $definition instanceof ParameterDefinition ? (string) $definition->getID() : '',
            'name' => '',
            'symbol' => '',
            'unit' => '',
            'value_text' => [
                'operator' => $operator,
                'value' => $value,
            ],
            'value' => [
                'operator' => '',
                'value1' => '',
                'value2' => '',
            ],
        ];
    }
}
