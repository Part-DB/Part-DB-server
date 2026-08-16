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
use App\Form\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

#[Group('DB')]
#[Group('slow')]
final class ParameterTypeTest extends KernelTestCase
{
    private EntityManagerInterface $entity_manager;
    private FormFactoryInterface $form_factory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entity_manager = static::getContainer()->get(EntityManagerInterface::class);
        $this->form_factory = static::getContainer()->get(FormFactoryInterface::class);
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

    /** @return array<string, mixed> */
    private function submission(
        ?ParameterDefinition $definition,
        string $value_text,
        ?string $name = null,
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
            'eda_visibility' => '',
            'eda_symbol_visibility' => '',
        ];
    }
}
