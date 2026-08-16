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

namespace App\Form\AdminPages;

use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Parameters\ParameterDefinition;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class ParameterDefinitionAdminForm extends BaseEntityAdminForm
{
    protected function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        if (!$entity instanceof ParameterDefinition) {
            throw new \InvalidArgumentException('ParameterDefinitionAdminForm expects a ParameterDefinition.');
        }

        $is_new = null === $entity->getID();
        $disabled = !$this->security->isGranted($is_new ? 'create' : 'edit', $entity);

        $builder
            ->add('symbol', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'parameter_definition.symbol',
                'disabled' => $disabled,
            ])
            ->add('unit', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'parameter_definition.unit',
                'disabled' => $disabled,
            ])
            ->add('input_type', ChoiceType::class, [
                'label' => 'parameter_definition.input_type',
                'choices' => [
                    'parameter_definition.input_type.text' => ParameterDefinition::INPUT_TYPE_TEXT,
                    'parameter_definition.input_type.choice' => ParameterDefinition::INPUT_TYPE_CHOICE,
                ],
                'disabled' => $disabled,
            ])
            ->add('choices_text', TextareaType::class, [
                'mapped' => false,
                'data' => $entity->getChoicesText(),
                'required' => false,
                'empty_data' => '',
                'label' => 'parameter_definition.choices',
                'help' => 'parameter_definition.choices.help',
                'attr' => ['rows' => 8],
                'disabled' => $disabled,
            ]);

        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            $definition = $event->getData();
            if (!$definition instanceof ParameterDefinition) {
                return;
            }

            if (ParameterDefinition::INPUT_TYPE_CHOICE === $definition->getInputType()) {
                $choices_text = $event->getForm()->get('choices_text')->getData();
                $definition->setChoicesText(is_string($choices_text) ? $choices_text : null);
            } else {
                $definition->setChoices(null);
            }
        });
    }
}
