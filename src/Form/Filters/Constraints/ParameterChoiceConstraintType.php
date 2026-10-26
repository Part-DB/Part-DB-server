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

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\TextConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ParameterChoiceConstraintType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('parameter_choices');
        $resolver->setAllowedTypes('parameter_choices', 'array');
        $resolver->setDefault('parameter_deprecated_choices', []);
        $resolver->setAllowedTypes('parameter_deprecated_choices', 'array');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($options['parameter_choices'] as $choice) {
            $choices[$choice] = $choice;
        }
        foreach ($options['parameter_deprecated_choices'] as $choice) {
            $choices[$this->translator->trans(
                'parameter_definition.choice.deprecated_label',
                ['%choice%' => $choice],
            )] = $choice;
        }

        $builder->add('value', ChoiceType::class, [
            'choices' => $choices,
            'choice_translation_domain' => false,
            'required' => false,
            'placeholder' => 'selectpicker.nothing_selected',
            'empty_data' => '',
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $constraint = $event->getData();
            if ($constraint instanceof TextConstraint) {
                $constraint->setOperator('=');
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $submitted_data = $event->getData();
            if (is_array($submitted_data)) {
                $submitted_data['operator'] = '=';
                $event->setData($submitted_data);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            $constraint = $event->getData();
            if ($constraint instanceof TextConstraint) {
                $constraint->setOperator('=');
            }
        });
    }

    public function getParent(): string
    {
        return TextConstraintType::class;
    }
}
