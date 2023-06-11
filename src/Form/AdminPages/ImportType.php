<?php
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

declare(strict_types=1);

namespace App\Form\AdminPages;

use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Form\Type\StructuralEntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ImportType extends AbstractType
{
    public function __construct(protected Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $options['data'];

        //Disable import if user is not allowed to create elements.
        $entity = new $data['entity_class']();
        $perm_name = 'import';
        $disabled = !$this->security->isGranted($perm_name, $entity);

        $builder

            ->add('format', ChoiceType::class, [
                'choices' => [
                    'parts.import.format.auto' => 'auto',
                    'JSON' => 'json',
                    'XML' => 'xml',
                    'CSV' => 'csv',
                    'YAML' => 'yaml',
                ],
                'label' => 'export.format',
                'disabled' => $disabled,
            ])
            ->add('csv_delimiter', TextType::class, [
                'data' => ';',
                'label' => 'import.csv_separator',
                'disabled' => $disabled,
            ]);

        if ($entity instanceof AbstractStructuralDBElement) {
            $builder->add('parent', StructuralEntityType::class, [
                'class' => $data['entity_class'],
                'required' => false,
                'label' => 'parent.label',
                'disabled' => $disabled,
            ]);
        }

        if ($entity instanceof Part) {
            $builder->add('part_category', StructuralEntityType::class, [
                'class' => Category::class,
                'required' => false,
                'label' => 'parts.import.part_category.label',
                'help' => 'parts.import.part_category.help',
                'disabled' => $disabled,
                'disable_not_selectable' => true,
                'allow_add' => true
            ]);
            $builder->add('part_needs_review', CheckboxType::class, [
                'data' => false,
                'required' => false,
                'label' => 'parts.import.part_needs_review.label',
                'help' => 'parts.import.part_needs_review.help',
                'disabled' => $disabled,
            ]);
        }

        if ($entity instanceof AbstractStructuralDBElement) {
            $builder->add('preserve_children', CheckboxType::class, [
                'data' => true,
                'required' => false,
                'label' => 'import.preserve_children',
                'disabled' => $disabled,
            ]);
        }

        if ($entity instanceof Part) {
            $builder->add('create_unknown_datastructures', CheckboxType::class, [
                'data' => true,
                'required' => false,
                'label' => 'import.create_unknown_datastructures',
                'help' => 'import.create_unknown_datastructures.help',
                'disabled' => $disabled,
            ]);

            $builder->add('path_delimiter', TextType::class, [
                'data' => '->',
                'label' => 'import.path_delimiter',
                'help' => 'import.path_delimiter.help',
                'disabled' => $disabled,
            ]);
        }

        $builder->add('file', FileType::class, [
            'label' => 'import.file',
            'attr' => [
                'class' => 'file',
                'data-show-preview' => 'false',
                'data-show-upload' => 'false',
            ],
            'disabled' => $disabled,
        ]);

        $builder->add('abort_on_validation_error', CheckboxType::class, [
            'data' => true,
            'required' => false,
            'label' => 'import.abort_on_validation',
            'help' => 'import.abort_on_validation.help',
            'disabled' => $disabled,
        ])

            //Buttons
            ->add('import', SubmitType::class, [
                'label' => 'import.btn',
                'disabled' => $disabled,
            ]);
    }
}
