<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Form;

use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parameters\AttachmentTypeParameter;
use App\Entity\Parameters\CategoryParameter;
use App\Entity\Parameters\CurrencyParameter;
use App\Entity\Parameters\ProjectParameter;
use App\Entity\Parameters\FootprintParameter;
use App\Entity\Parameters\GroupParameter;
use App\Entity\Parameters\ManufacturerParameter;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parameters\ParameterDefinition;
use App\Entity\Parameters\StorageLocationParameter;
use App\Entity\Parameters\SupplierParameter;
use App\Entity\Parts\MeasurementUnit;
use App\Form\Type\ExponentialNumberType;
use App\Form\Type\TriStateCheckboxType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParameterType extends AbstractType
{
    public function __construct(private readonly EntityManagerInterface $entity_manager)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $parameter = $builder->getData();
        $linked_part_parameter = $parameter instanceof PartParameter
            && $parameter->getDefinition() instanceof ParameterDefinition;

        $name_options = [
            'label' => false,
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'parameters.name.placeholder',
                'class' => 'form-control-sm',
            ],
        ];
        if ($linked_part_parameter) {
            $name_options['data'] = $parameter->getEffectiveName();
        }
        $builder->add('name', TextType::class, $name_options);

        $symbol_options = [
            'label' => false,
            'required' => false,
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'parameters.symbol.placeholder',
                'class' => 'form-control-sm',
                'style' => 'max-width: 12ch;',
            ],
        ];
        if ($linked_part_parameter) {
            $symbol_options['data'] = $parameter->getEffectiveSymbol();
        }
        $builder->add('symbol', TextType::class, $symbol_options);

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (PreSetDataEvent $event): void {
                $parameter = $event->getData();
                $this->addValueTextField(
                    $event->getForm(),
                    $parameter instanceof PartParameter
                        ? $parameter->getEffectiveInputType()
                        : ParameterDefinition::INPUT_TYPE_TEXT,
                    $parameter instanceof PartParameter ? $parameter->getEffectiveChoices() : [],
                );
            }
        );

        $builder->add('value_max', ExponentialNumberType::class, [
            'label' => false,
            'required' => false,
            'html5' => true,
            'attr' => [
                'step' => 'any',
                'placeholder' => 'parameters.max.placeholder',
                'class' => 'form-control-sm',
                'style' => 'max-width: 25ch;',
            ],
        ]);
        $builder->add('value_min', ExponentialNumberType::class, [
            'label' => false,
            'required' => false,
            'html5' => true,
            'attr' => [
                'step' => 'any',
                'placeholder' => 'parameters.min.placeholder',
                'class' => 'form-control-sm',
                'style' => 'max-width: 25ch;',
            ],
        ]);
        $builder->add('value_typical', ExponentialNumberType::class, [
            'label' => false,
            'required' => false,
            'html5' => true,
            'attr' => [
                'step' => 'any',
                'placeholder' => 'parameters.typical.placeholder',
                'class' => 'form-control-sm',
                'style' => 'max-width: 25ch;',
            ],
        ]);
        $unit_options = [
            'label' => false,
            'required' => false,
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'parameters.unit.placeholder',
                'class' => 'form-control-sm',
                'style' => 'max-width: 8ch;',
            ],
        ];
        if ($linked_part_parameter) {
            $unit_options['data'] = $parameter->getEffectiveUnit();
        }
        $builder->add('unit', TextType::class, $unit_options);

        $builder->add('group', TextType::class, [
            'label' => false,
            'required' => false,
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'parameter.group.placeholder',
                'class' => 'form-control-sm',
            ],
        ]);
        // Only show the EDA visibility field for part parameters, as it has no function for other entities
        if ($options['data_class'] === PartParameter::class) {
            $builder->add('definition', EntityType::class, [
                'class' => ParameterDefinition::class,
                'choice_label' => 'name',
                'choice_lazy' => true,
                'label' => false,
                'required' => false,
                'placeholder' => '',
                'attr' => [
                    'class' => 'd-none',
                ],
            ]);

            $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
                $submitted_data = $event->getData();
                $definition = null;

                if (is_array($submitted_data)) {
                    $definition_id = filter_var(
                        $submitted_data['definition'] ?? null,
                        FILTER_VALIDATE_INT,
                        ['options' => ['min_range' => 1]],
                    );
                    if (false !== $definition_id) {
                        $definition = $this->entity_manager->find(ParameterDefinition::class, $definition_id);
                    }
                }

                $this->addValueTextField(
                    $event->getForm(),
                    $definition?->getInputType() ?? ParameterDefinition::INPUT_TYPE_TEXT,
                    $definition?->getChoices() ?? [],
                );
            });

            $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
                $parameter = $event->getData();
                if ($parameter instanceof PartParameter && $parameter->getDefinition() instanceof ParameterDefinition) {
                    $parameter->refreshSnapshotFromDefinition();
                }
            });

            $builder->add('eda_visibility', TriStateCheckboxType::class, [
                'label' => false,
                'required' => false,
            ]);

            $builder->add('eda_symbol_visibility', TriStateCheckboxType::class, [
                'label' => false,
                'required' => false,
            ]);

        }
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        //By default use part parameters for autocomplete
        $view->vars['type'] = 'part';

        $map = [
            PartParameter::class => 'part',
            AttachmentTypeParameter::class => 'attachment_type',
            CategoryParameter::class => 'category',
            CurrencyParameter::class => 'currency',
            ProjectParameter::class => 'device',
            FootprintParameter::class => 'footprint',
            GroupParameter::class => 'group',
            ManufacturerParameter::class => 'manufacturer',
            MeasurementUnit::class => 'measurement_unit',
            StorageLocationParameter::class => 'storelocation',
            SupplierParameter::class => 'supplier',
        ];

        if (isset($map[$options['data_class']])) {
            $view->vars['type'] = $map[$options['data_class']];
        }

        parent::finishView($view, $form, $options); // TODO: Change the autogenerated stub
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AbstractParameter::class,
        ]);
    }

    /** @param list<string> $choices */
    private function addValueTextField(FormInterface $form, string $input_type, array $choices): void
    {
        if (ParameterDefinition::INPUT_TYPE_CHOICE === $input_type) {
            $choice_map = [];
            foreach ($choices as $choice) {
                $choice_map[$choice] = $choice;
            }

            $form->add('value_text', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'placeholder' => '',
                'choices' => $choice_map,
                'attr' => [
                    'class' => 'form-select-sm',
                ],
            ]);

            return;
        }

        $form->add('value_text', TextType::class, [
            'label' => false,
            'required' => false,
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'parameters.text.placeholder',
                'class' => 'form-control-sm',
            ],
        ]);
    }
}
