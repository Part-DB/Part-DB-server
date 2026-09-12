<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace App\Tests\Form;

use App\Entity\Parameters\ParameterDefinition;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use App\Form\ParameterType;
use App\Services\Parameters\PendingParameterChoiceApplier;
use App\Services\UserSystem\PermissionSchemaUpdater;
use App\Validator\Constraints\UniqueObjectCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Group('DB')]
#[Group('slow')]
final class ParameterTypeTest extends KernelTestCase
{
    private EntityManagerInterface $entity_manager;
    private FormFactoryInterface $form_factory;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entity_manager = static::getContainer()->get(EntityManagerInterface::class);
        $this->form_factory = static::getContainer()->get(FormFactoryInterface::class);
        $this->validator = static::getContainer()->get(ValidatorInterface::class);
    }

    public function testChoiceDefinitionIsLinkedAndRestrictsSubmittedValue(): void
    {
        $definition = $this->createDefinition(
            'Form dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R', 'X5R', 'C0G'],
        );
        $parameter = new PartParameter();
        $form = $this->createParameterForm($parameter);

        $form->submit($this->submission($definition, 'X7R'));

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($definition, $parameter->getDefinition());
        self::assertSame('X7R', $parameter->getValueText());
        self::assertSame('Form dielectric', $parameter->getSnapshotName());
        self::assertInstanceOf(ChoiceType::class, $form->get('value_text')->getConfig()->getType()->getInnerType());

        $invalid_parameter = new PartParameter();
        $invalid_form = $this->createParameterForm($invalid_parameter);
        $invalid_form->submit($this->submission($definition, 'Tantalum'));

        self::assertFalse($invalid_form->isValid());
    }

    public function testTextDefinitionKeepsFreeTextInput(): void
    {
        $definition = $this->createDefinition('Form manufacturer code', ParameterDefinition::INPUT_TYPE_TEXT);
        $parameter = new PartParameter();
        $form = $this->createParameterForm($parameter);

        $form->submit($this->submission($definition, 'free-form code'));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($definition, $parameter->getDefinition());
        self::assertSame('free-form code', $parameter->getValueText());
        self::assertInstanceOf(TextType::class, $form->get('value_text')->getConfig()->getType()->getInnerType());
    }

    public function testParameterWithoutDefinitionKeepsLegacyTextBehavior(): void
    {
        $parameter = new PartParameter();
        $form = $this->createParameterForm($parameter);

        $form->submit($this->submission(null, 'legacy free text', 'My custom parameter'));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertNull($parameter->getDefinition());
        self::assertSame('My custom parameter', $parameter->getName());
        self::assertSame('legacy free text', $parameter->getValueText());
        self::assertInstanceOf(TextType::class, $form->get('value_text')->getConfig()->getType()->getInnerType());
    }

    public function testDefinitionTransitionsRebuildValueFieldWithoutResidualValidation(): void
    {
        $choice_definition = $this->createDefinition(
            'Form transition choice',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R', 'C0G'],
        );
        $text_definition = $this->createDefinition('Form transition text', ParameterDefinition::INPUT_TYPE_TEXT);
        $parameter = (new PartParameter())
            ->setDefinition($choice_definition)
            ->setValueText('X7R');

        $text_form = $this->createParameterForm($parameter);
        self::assertInstanceOf(ChoiceType::class, $text_form->get('value_text')->getConfig()->getType()->getInnerType());
        $text_form->submit($this->submission($text_definition, 'now free text'));

        self::assertTrue($text_form->isValid(), (string) $text_form->getErrors(true));
        self::assertSame($text_definition, $parameter->getDefinition());
        self::assertSame('now free text', $parameter->getValueText());
        self::assertInstanceOf(TextType::class, $text_form->get('value_text')->getConfig()->getType()->getInnerType());

        $choice_form = $this->createParameterForm($parameter);
        $choice_form->submit($this->submission($choice_definition, 'C0G'));

        self::assertTrue($choice_form->isValid(), (string) $choice_form->getErrors(true));
        self::assertSame($choice_definition, $parameter->getDefinition());
        self::assertSame('C0G', $parameter->getValueText());
        self::assertInstanceOf(ChoiceType::class, $choice_form->get('value_text')->getConfig()->getType()->getInnerType());
    }

    public function testPersistedChoiceParameterReopensWithItsSelectedChoice(): void
    {
        $definition = $this->createDefinition(
            'Form persisted dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R', 'X5R', 'C0G'],
        );
        $category = (new Category())->setName('Form persisted category');
        $part = (new Part())->setName('Form persisted part')->setCategory($category);
        $parameter = new PartParameter();
        $part->addParameter($parameter);

        $form = $this->createParameterForm($parameter);
        $form->submit($this->submission($definition, 'X7R'));
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        $this->entity_manager->persist($category);
        $this->entity_manager->persist($part);
        $this->entity_manager->flush();
        $parameter_id = $parameter->getID();
        $this->entity_manager->clear();

        $reloaded = $this->entity_manager->find(PartParameter::class, $parameter_id);
        self::assertInstanceOf(PartParameter::class, $reloaded);
        $reopened_form = $this->createParameterForm($reloaded);

        self::assertInstanceOf(ChoiceType::class, $reopened_form->get('value_text')->getConfig()->getType()->getInnerType());
        self::assertSame('X7R', $reopened_form->get('value_text')->getData());
        self::assertSame('X7R', $reopened_form->createView()['value_text']->vars['value']);
    }

    /** CHOICE-DEPRECATION-005, CHOICE-DEPRECATION-013 */
    public function testExistingDeprecatedChoiceIsRenderedAndSurvivesUnchangedSubmit(): void
    {
        [$definition, $_part, $parameter] = $this->createPersistedChoiceParameter(
            'Form deprecated dielectric',
            'X7R',
        );
        $definition->setChoices(['X5R']);
        $this->entity_manager->flush();
        $parameter_id = $parameter->getID();
        self::assertNotNull($parameter_id);
        $this->entity_manager->clear();

        $reloaded = $this->entity_manager->find(PartParameter::class, $parameter_id);
        self::assertInstanceOf(PartParameter::class, $reloaded);
        self::assertSame('X7R', $reloaded->getValueText());
        self::assertSame(['X7R'], $reloaded->getDefinition()?->getDeprecatedChoices());
        $form = $this->createParameterForm($reloaded);
        self::assertSame(
            ['X5R' => 'X5R', 'X7R (deprecated)' => 'X7R'],
            $form->get('value_text')->getConfig()->getOption('choices'),
        );

        $form->submit($this->submission($definition, 'X7R'));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('X7R', $reloaded->getValueText());
        self::assertCount(0, $this->validator->validate($reloaded));
    }

    /** CHOICE-DEPRECATION-006, CHOICE-DEPRECATION-011, CHOICE-DEPRECATION-014 */
    public function testNewParameterDoesNotOfferDeprecatedChoices(): void
    {
        $definition = $this->createDefinition(
            'Form new row deprecated dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['C0G', 'X5R'],
        )->setDeprecatedChoices(['X7R']);
        $parameter = new PartParameter();
        $form = $this->createParameterForm($parameter);

        $form->submit($this->submission($definition, ''));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('', $parameter->getValueText());
        self::assertSame(
            ['C0G' => 'C0G', 'X5R' => 'X5R'],
            $form->get('value_text')->getConfig()->getOption('choices'),
        );
    }

    /** CHOICE-DEPRECATION-007 */
    public function testDeprecatedChoiceCanBeChangedToActiveOrNotDefined(): void
    {
        $definition = $this->createDefinition(
            'Form change deprecated dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['C0G', 'X5R'],
        )->setDeprecatedChoices(['X7R']);
        $parameter = (new PartParameter())->setDefinition($definition)->setValueText('X7R');
        $form = $this->createParameterForm($parameter);

        $form->submit($this->submission($definition, 'X5R'));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('X5R', $parameter->getValueText());
        self::assertArrayNotHasKey(
            'X7R (deprecated)',
            $this->createParameterForm($parameter)->get('value_text')->getConfig()->getOption('choices'),
        );

        $empty_parameter = (new PartParameter())->setDefinition($definition)->setValueText('X7R');
        $empty_form = $this->createParameterForm($empty_parameter);
        $empty_form->submit($this->submission($definition, ''));

        self::assertTrue($empty_form->isValid(), (string) $empty_form->getErrors(true));
        self::assertSame('', $empty_parameter->getValueText());
    }

    /** CHOICE-DEPRECATION-008, CHOICE-DEPRECATION-015 */
    public function testForgedDeprecatedChoiceIsRejectedUnlessItIsCurrentValue(): void
    {
        $definition = $this->createDefinition(
            'Form forged deprecated dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['C0G', 'X5R'],
        )->setDeprecatedChoices(['X7R']);

        $new_form = $this->createParameterForm(new PartParameter());
        $new_form->submit($this->submission($definition, 'X7R'));
        self::assertFalse($new_form->isValid());

        $existing_parameter = (new PartParameter())->setDefinition($definition)->setValueText('C0G');
        $existing_form = $this->createParameterForm($existing_parameter);
        $existing_form->submit($this->submission($definition, 'X7R'));
        self::assertFalse($existing_form->isValid());
    }

    public function testEmptyChoiceCanBeSubmittedAndReopenedWithoutChangingDefinition(): void
    {
        $this->loginAs('admin');
        $definition = $this->createDefinition(
            'Form empty dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R', 'X5R'],
        );
        $category = (new Category())->setName('Form empty category');
        $part = (new Part())->setName('Form empty part')->setCategory($category);
        $parameter = new PartParameter();
        $part->addParameter($parameter);

        $form = $this->createParameterForm($parameter);
        $form->submit($this->submission($definition, ''));

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('', $parameter->getValueText());
        self::assertSame($definition, $parameter->getDefinition());
        self::assertSame(['X7R', 'X5R'], $definition->getChoices());

        $this->entity_manager->persist($category);
        $this->entity_manager->persist($part);
        $this->entity_manager->flush();
        $parameter_id = $parameter->getID();
        $this->entity_manager->clear();

        $reloaded = $this->entity_manager->find(PartParameter::class, $parameter_id);
        self::assertInstanceOf(PartParameter::class, $reloaded);
        self::assertSame('', $reloaded->getValueText());
        self::assertInstanceOf(ParameterDefinition::class, $reloaded->getDefinition());
        self::assertSame(['X7R', 'X5R'], $reloaded->getDefinition()->getChoices());
        $reopened_form = $this->createParameterForm($reloaded);
        self::assertSame('', $reopened_form->get('value_text')->getData());
        self::assertSame(
            'true',
            $reopened_form->get('value_text')->getConfig()->getOption('attr')['data-can-add-choice'],
        );
        self::assertSame('', $reopened_form->get('value_text')->getConfig()->getOption('attr')['data-controller']);

        $selected_parameter = (new PartParameter())->setDefinition($reloaded->getDefinition())->setValueText('X7R');
        $selected_form = $this->createParameterForm($selected_parameter);
        self::assertSame('X7R', $selected_form->get('value_text')->getData());
        self::assertSame(
            'true',
            $selected_form->get('value_text')->getConfig()->getOption('attr')['data-can-add-choice'],
        );
        self::assertSame('', $selected_form->get('value_text')->getConfig()->getOption('attr')['data-controller']);
    }

    public function testExistingChoiceCanBeClearedAndReopened(): void
    {
        $definition = $this->createDefinition(
            'Form cleared dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R', 'X5R'],
        );
        $category = (new Category())->setName('Form cleared category');
        $part = (new Part())->setName('Form cleared part')->setCategory($category);
        $parameter = (new PartParameter())->setDefinition($definition)->setValueText('X7R');
        $part->addParameter($parameter);
        $this->entity_manager->persist($category);
        $this->entity_manager->persist($part);
        $this->entity_manager->flush();

        $form = $this->createParameterForm($parameter);
        $form->submit($this->submission($definition, ''));
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->entity_manager->flush();
        $parameter_id = $parameter->getID();
        $this->entity_manager->clear();

        $reloaded = $this->entity_manager->find(PartParameter::class, $parameter_id);
        self::assertInstanceOf(PartParameter::class, $reloaded);
        self::assertSame('', $reloaded->getValueText());
        self::assertSame(['X7R', 'X5R'], $reloaded->getDefinition()?->getChoices());
    }

    public function testNewChoiceStaysPendingUntilSuccessfulPartSave(): void
    {
        $this->loginAs('admin');
        $definition = $this->createDefinition(
            'Form pending dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R', 'X5R'],
        );
        $category = (new Category())->setName('Form pending category');
        $part = (new Part())->setName('Form pending part')->setCategory($category);
        $parameter = new PartParameter();
        $part->addParameter($parameter);

        $form = $this->createParameterForm($parameter);
        $form->submit($this->submission($definition, 'X6S', new_choice_value: ' X6S '));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('X6S', $parameter->getPendingDefinitionChoice());
        self::assertSame(['X7R', 'X5R'], $definition->getChoices(), 'The form alone must not mutate the definition.');

        static::getContainer()->get(PendingParameterChoiceApplier::class)->apply($part);
        self::assertSame(['X7R', 'X5R', 'X6S'], $definition->getChoices());
        self::assertSame('X6S', $parameter->getValueText());
        self::assertNull($parameter->getPendingDefinitionChoice());

        $this->entity_manager->persist($category);
        $this->entity_manager->persist($part);
        $this->entity_manager->flush();
        $parameter_id = $parameter->getID();
        $this->entity_manager->clear();

        $reloaded = $this->entity_manager->find(PartParameter::class, $parameter_id);
        self::assertInstanceOf(PartParameter::class, $reloaded);
        self::assertSame('X6S', $reloaded->getValueText());
        self::assertContains('X6S', $reloaded->getDefinition()?->getChoices() ?? []);
    }

    public function testCanonicalAndBlankPendingValuesNeverCreateChoices(): void
    {
        $this->loginAs('admin');
        $definition = $this->createDefinition(
            'Form canonical dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R'],
        );

        $canonical_parameter = new PartParameter();
        $canonical_form = $this->createParameterForm($canonical_parameter);
        $canonical_form->submit($this->submission($definition, ' x7r ', new_choice_value: ' x7r '));
        self::assertTrue($canonical_form->isValid(), (string) $canonical_form->getErrors(true));
        self::assertSame('X7R', $canonical_parameter->getValueText());
        self::assertNull($canonical_parameter->getPendingDefinitionChoice());

        $blank_parameter = new PartParameter();
        $blank_form = $this->createParameterForm($blank_parameter);
        $blank_form->submit($this->submission($definition, '   ', new_choice_value: '   '));
        self::assertTrue($blank_form->isValid(), (string) $blank_form->getErrors(true));
        self::assertSame('', $blank_parameter->getValueText());
        self::assertNull($blank_parameter->getPendingDefinitionChoice());
        self::assertSame(['X7R'], $definition->getChoices());
    }

    public function testStalePendingForExistingChoiceIsClearedWithoutApplyingDefinition(): void
    {
        $this->loginAs('admin');
        $definition = $this->createDefinition(
            'Form stale pending dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R', 'X5R'],
        );
        $parameter = new PartParameter();
        $part = (new Part())->addParameter($parameter);
        $form = $this->createParameterForm($parameter);

        $form->submit($this->submission($definition, 'X7R', new_choice_value: ' x7r '));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('X7R', $parameter->getValueText());
        self::assertNull($parameter->getPendingDefinitionChoice());
        self::assertEmpty($form->get('new_choice_value')->getData());

        static::getContainer()->get(PendingParameterChoiceApplier::class)->apply($part);
        self::assertSame(['X7R', 'X5R'], $definition->getChoices());
    }

    public function testStalePendingCannotOverrideAnotherVisibleExistingChoice(): void
    {
        $this->loginAs('admin');
        $definition = $this->createDefinition(
            'Form divergent stale pending dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R', 'X5R'],
        );
        $parameter = new PartParameter();
        $part = (new Part())->addParameter($parameter);
        $form = $this->createParameterForm($parameter);

        $form->submit($this->submission($definition, 'X5R', new_choice_value: 'x7r'));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('X5R', $parameter->getValueText());
        self::assertNull($parameter->getPendingDefinitionChoice());
        self::assertEmpty($form->get('new_choice_value')->getData());

        static::getContainer()->get(PendingParameterChoiceApplier::class)->apply($part);
        self::assertSame(['X7R', 'X5R'], $definition->getChoices());
    }

    public function testRepeatedSubmissionOfNewlySavedChoiceDoesNotAddItAgain(): void
    {
        $this->loginAs('admin');
        $definition = $this->createDefinition(
            'Form repeated pending dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R'],
        );
        $parameter = new PartParameter();
        $part = (new Part())->addParameter($parameter);

        $first_form = $this->createParameterForm($parameter);
        $first_form->submit($this->submission($definition, 'X6S', new_choice_value: 'X6S'));
        self::assertTrue($first_form->isValid(), (string) $first_form->getErrors(true));
        static::getContainer()->get(PendingParameterChoiceApplier::class)->apply($part);
        self::assertSame(['X7R', 'X6S'], $definition->getChoices());

        $second_form = $this->createParameterForm($parameter);
        $second_form->submit($this->submission($definition, 'X6S', new_choice_value: 'X6S'));
        self::assertTrue($second_form->isValid(), (string) $second_form->getErrors(true));
        self::assertNull($parameter->getPendingDefinitionChoice());
        static::getContainer()->get(PendingParameterChoiceApplier::class)->apply($part);
        self::assertSame(['X7R', 'X6S'], $definition->getChoices());
    }

    public function testUserWithoutDefinitionEditPermissionCanUseExistingOrEmptyButNotNewChoice(): void
    {
        $this->loginAs('user');
        $definition = $this->createDefinition(
            'Form permission dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R'],
        );

        foreach (['X7R', ''] as $value) {
            $form = $this->createParameterForm(new PartParameter());
            self::assertSame(
                'false',
                $form->get('value_text')->getConfig()->getOption('attr')['data-can-add-choice'],
            );
            $form->submit($this->submission($definition, $value));
            self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        }

        $parameter = new PartParameter();
        $form = $this->createParameterForm($parameter);
        $form->submit($this->submission($definition, 'X6S', new_choice_value: 'X6S'));

        self::assertFalse($form->isValid());
        self::assertSame(['X7R'], $definition->getChoices());
        self::assertNull($parameter->getPendingDefinitionChoice());
    }

    public function testPendingChoiceDoesNotMutateDefinitionWhenAnotherFormFieldIsInvalid(): void
    {
        $this->loginAs('admin');
        $definition = $this->createDefinition(
            'Form invalid-root dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R'],
        );
        $parameter = new PartParameter();
        $part = (new Part())->addParameter($parameter);
        $form = $this->form_factory->createBuilder(FormType::class, ['parameter' => $parameter])
            ->add('parameter', ParameterType::class, [
                'data_class' => PartParameter::class,
                'csrf_protection' => false,
            ])
            ->add('required_field', TextType::class, [
                'mapped' => false,
                'constraints' => [new NotBlank()],
            ])
            ->getForm();

        $form->submit([
            'parameter' => $this->submission($definition, 'X6S', new_choice_value: 'X6S'),
            'required_field' => '',
        ]);

        self::assertFalse($form->isValid());
        self::assertSame('X6S', $parameter->getPendingDefinitionChoice());
        // This is the same guard used by PartController: the applier is never called for an invalid root form.
        if ($form->isValid()) {
            static::getContainer()->get(PendingParameterChoiceApplier::class)->apply($part);
        }
        self::assertSame(['X7R'], $definition->getChoices());
    }

    public function testPendingChoiceApplierEnforcesDefinitionEditPermission(): void
    {
        $this->loginAs('user');
        $definition = $this->createDefinition(
            'Form forged pending dielectric',
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R'],
        );
        $parameter = (new PartParameter())
            ->setDefinition($definition)
            ->setValueText('X6S')
            ->requestPendingDefinitionChoice('X6S');
        $part = (new Part())->addParameter($parameter);

        try {
            static::getContainer()->get(PendingParameterChoiceApplier::class)->apply($part);
            self::fail('A forged pending choice must be denied.');
        } catch (AccessDeniedException) {
            self::assertSame(['X7R'], $definition->getChoices());
            self::assertSame('X6S', $parameter->getPendingDefinitionChoice());
        }
    }

    public function testPersistedChoiceCanBeRemovedAndRecreatedInTheSameUnitOfWork(): void
    {
        [$definition, $part, $old_parameter] = $this->createPersistedChoiceParameter(
            'Form same request dielectric',
            'X7R',
        );
        $old_parameter_id = $old_parameter->getID();

        $part->removeParameter($old_parameter);
        $new_parameter = new PartParameter();
        $part->addParameter($new_parameter);
        $form = $this->createParameterForm($new_parameter);
        $form->submit($this->submission($definition, 'X7R'));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertFalse($part->getParameters()->contains($old_parameter));
        self::assertTrue($part->getParameters()->contains($new_parameter));
        self::assertInstanceOf(PartParameter::class, $this->entity_manager->find(PartParameter::class, $old_parameter_id));
        self::assertNull($old_parameter->getDefinition());
        self::assertFalse($definition->getParameterUsages()->contains($old_parameter));
        self::assertTrue($definition->getParameterUsages()->contains($new_parameter));
        self::assertSame('X7R', $new_parameter->getValueText());
        self::assertNull($new_parameter->getPendingDefinitionChoice());
        self::assertEmpty($form->get('new_choice_value')->getData());
        self::assertCount(0, $this->validator->validate($part));

        static::getContainer()->get(PendingParameterChoiceApplier::class)->apply($part);
        self::assertSame(['X7R', 'X5R'], $definition->getChoices());
        $this->entity_manager->persist($part);
        $this->entity_manager->flush();
        $new_parameter_id = $new_parameter->getID();
        $part_id = $part->getID();
        $this->entity_manager->clear();

        $reloaded_part = $this->entity_manager->find(Part::class, $part_id);
        self::assertInstanceOf(Part::class, $reloaded_part);
        self::assertCount(1, $reloaded_part->getParameters());
        $reloaded_parameter = $reloaded_part->getParameters()->first();
        self::assertInstanceOf(PartParameter::class, $reloaded_parameter);
        self::assertSame($new_parameter_id, $reloaded_parameter->getID());
        self::assertSame('X7R', $reloaded_parameter->getValueText());
        self::assertNull($this->entity_manager->find(PartParameter::class, $old_parameter_id));
    }

    public function testPersistedChoiceCanBeRemovedAndRecreatedAfterAnIntermediateFlush(): void
    {
        [$definition, $part, $old_parameter] = $this->createPersistedChoiceParameter(
            'Form separate requests dielectric',
            'X7R',
        );
        $old_parameter_id = $old_parameter->getID();
        $part_id = $part->getID();

        $part->removeParameter($old_parameter);
        $this->entity_manager->flush();
        self::assertNull($this->entity_manager->find(PartParameter::class, $old_parameter_id));
        $this->entity_manager->clear();

        $part = $this->entity_manager->find(Part::class, $part_id);
        $definition = $this->entity_manager->find(ParameterDefinition::class, $definition->getID());
        self::assertInstanceOf(Part::class, $part);
        self::assertInstanceOf(ParameterDefinition::class, $definition);
        $new_parameter = new PartParameter();
        $part->addParameter($new_parameter);
        $form = $this->createParameterForm($new_parameter);
        $form->submit($this->submission($definition, 'X7R'));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertCount(0, $this->validator->validate($part));
        $this->entity_manager->persist($part);
        $this->entity_manager->flush();
        $this->entity_manager->clear();

        $reloaded_part = $this->entity_manager->find(Part::class, $part_id);
        self::assertInstanceOf(Part::class, $reloaded_part);
        self::assertCount(1, $reloaded_part->getParameters());
        $reloaded_parameter = $reloaded_part->getParameters()->first();
        self::assertInstanceOf(PartParameter::class, $reloaded_parameter);
        self::assertSame('X7R', $reloaded_parameter->getValueText());
    }

    public function testTwoSimultaneouslyActiveParametersWithTheSameNameRemainInvalid(): void
    {
        [$definition, $part] = $this->createPersistedChoiceParameter('Form active duplicate dielectric', 'X7R');
        $duplicate = new PartParameter();
        $part->addParameter($duplicate);
        $form = $this->createParameterForm($duplicate);
        $form->submit($this->submission($definition, 'X5R'));

        self::assertFalse($form->isValid(), 'UniqueEntity must reject a second active persisted name/group.');
        $violations = $this->validator->validate($part);
        self::assertGreaterThan(0, $violations->count());
        $collection_violation_found = false;
        foreach ($violations as $violation) {
            if ($violation->getCode() === UniqueObjectCollection::IS_NOT_UNIQUE) {
                $collection_violation_found = true;
                break;
            }
        }
        self::assertTrue($collection_violation_found, 'The active collection duplicate protection must remain enabled.');
    }

    public function testChoiceCanBeRemovedAndRecreatedEmptyInTheSameUnitOfWork(): void
    {
        [$definition, $part, $old_parameter] = $this->createPersistedChoiceParameter(
            'Form replacement empty dielectric',
            '',
        );

        $part->removeParameter($old_parameter);
        $new_parameter = new PartParameter();
        $part->addParameter($new_parameter);
        $form = $this->createParameterForm($new_parameter);
        $form->submit($this->submission($definition, ''));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertCount(0, $this->validator->validate($part));
        self::assertSame('', $new_parameter->getValueText());
        self::assertNull($new_parameter->getPendingDefinitionChoice());
        $this->entity_manager->persist($part);
        $this->entity_manager->flush();
        self::assertCount(1, $part->getParameters());
    }

    public function testChoiceCanBeRemovedAndRecreatedWithAnotherExistingChoice(): void
    {
        [$definition, $part, $old_parameter] = $this->createPersistedChoiceParameter(
            'Form replacement other dielectric',
            'X7R',
        );

        $part->removeParameter($old_parameter);
        $new_parameter = new PartParameter();
        $part->addParameter($new_parameter);
        $form = $this->createParameterForm($new_parameter);
        $form->submit($this->submission($definition, 'X5R'));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertCount(0, $this->validator->validate($part));
        self::assertSame('X5R', $new_parameter->getValueText());
        self::assertNull($new_parameter->getPendingDefinitionChoice());
        $this->entity_manager->persist($part);
        $this->entity_manager->flush();
        self::assertCount(1, $part->getParameters());
    }

    public function testChoiceCanBeRemovedAndRecreatedWithOnePendingNewChoice(): void
    {
        $this->loginAs('admin');
        [$definition, $part, $old_parameter] = $this->createPersistedChoiceParameter(
            'Form replacement new dielectric',
            'X7R',
        );

        $part->removeParameter($old_parameter);
        $new_parameter = new PartParameter();
        $part->addParameter($new_parameter);
        $form = $this->createParameterForm($new_parameter);
        $form->submit($this->submission($definition, 'X6R', new_choice_value: 'X6R'));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertCount(0, $this->validator->validate($part));
        self::assertSame(['X7R', 'X5R'], $definition->getChoices());
        self::assertSame('X6R', $new_parameter->getPendingDefinitionChoice());

        static::getContainer()->get(PendingParameterChoiceApplier::class)->apply($part);
        self::assertSame(['X7R', 'X5R', 'X6R'], $definition->getChoices());
        self::assertSame(1, array_count_values($definition->getChoices())['X6R']);
        self::assertNull($new_parameter->getPendingDefinitionChoice());
        $this->entity_manager->persist($part);
        $this->entity_manager->flush();
        self::assertCount(1, $part->getParameters());
        $saved_parameter = $part->getParameters()->first();
        self::assertInstanceOf(PartParameter::class, $saved_parameter);
        self::assertSame('X6R', $saved_parameter->getValueText());
    }

    public function testLinkedParameterEditorUsesCurrentDefinitionMetadataWithoutChangingSnapshots(): void
    {
        $definition = $this->createDefinition('Original form name', ParameterDefinition::INPUT_TYPE_TEXT);
        $definition->setSymbol('old')->setUnit('old-unit');
        $parameter = (new PartParameter())->setDefinition($definition);

        $definition
            ->setName('Current form name')
            ->setSymbol('new')
            ->setUnit('new-unit');

        $form = $this->createParameterForm($parameter);

        self::assertSame('Original form name', $parameter->getSnapshotName());
        self::assertSame('old', $parameter->getSnapshotSymbol());
        self::assertSame('old-unit', $parameter->getSnapshotUnit());
        self::assertSame('Current form name', $form->get('name')->getData());
        self::assertSame('new', $form->get('symbol')->getData());
        self::assertSame('new-unit', $form->get('unit')->getData());
        self::assertTrue($form->get('symbol')->getConfig()->getOption('attr')['readonly']);
        self::assertTrue($form->get('unit')->getConfig()->getOption('attr')['readonly']);
    }

    /** @param list<string>|null $choices */
    private function createDefinition(string $name, string $input_type, ?array $choices = null): ParameterDefinition
    {
        $definition = (new ParameterDefinition())
            ->setName($name)
            ->setInputType($input_type)
            ->setChoices($choices);
        $this->entity_manager->persist($definition);
        $this->entity_manager->flush();

        return $definition;
    }

    private function createParameterForm(PartParameter $parameter): FormInterface
    {
        return $this->form_factory->create(ParameterType::class, $parameter, [
            'data_class' => PartParameter::class,
            'csrf_protection' => false,
        ]);
    }

    /** @return array{ParameterDefinition, Part, PartParameter} */
    private function createPersistedChoiceParameter(string $definition_name, string $value): array
    {
        $definition = $this->createDefinition(
            $definition_name,
            ParameterDefinition::INPUT_TYPE_CHOICE,
            ['X7R', 'X5R'],
        );
        $category = (new Category())->setName($definition_name . ' category');
        $part = (new Part())->setName($definition_name . ' part')->setCategory($category);
        $parameter = (new PartParameter())->setDefinition($definition)->setValueText($value);
        $part->addParameter($parameter);
        $this->entity_manager->persist($category);
        $this->entity_manager->persist($part);
        $this->entity_manager->flush();

        return [$definition, $part, $parameter];
    }

    private function loginAs(string $username): void
    {
        $user = $this->entity_manager->getRepository(User::class)->findOneBy(['name' => $username]);
        self::assertInstanceOf(User::class, $user);
        static::getContainer()->get(PermissionSchemaUpdater::class)->userUpgradeSchemaRecursively($user);
        $this->entity_manager->flush();
        static::getContainer()->get(TokenStorageInterface::class)->setToken(new UsernamePasswordToken($user, 'main'));
    }

    /** @return array<string, mixed> */
    private function submission(
        ?ParameterDefinition $definition,
        string $value_text,
        ?string $name = null,
        string $new_choice_value = '',
    ): array {
        return [
            'name' => $name ?? $definition?->getName() ?? '',
            'symbol' => $definition?->getSymbol() ?? '',
            'value_min' => '',
            'value_typical' => '',
            'value_max' => '',
            'unit' => $definition?->getUnit() ?? '',
            'value_text' => $value_text,
            'group' => '',
            'definition' => $definition instanceof ParameterDefinition ? (string) $definition->getID() : '',
            'new_choice_value' => $new_choice_value,
            'eda_visibility' => '',
            'eda_symbol_visibility' => '',
        ];
    }
}
