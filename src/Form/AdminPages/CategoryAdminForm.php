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

use App\Entity\Base\AbstractNamedDBElement;
use App\Form\Part\EDA\EDACategoryInfoType;
use App\Form\Type\RichTextEditorType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class CategoryAdminForm extends BaseEntityAdminForm
{
    protected function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        $is_new = null === $entity->getID();

        $builder->add('disable_footprints', CheckboxType::class, [
            'required' => false,
            'label' => 'category.edit.disable_footprints',
            'help' => 'category.edit.disable_footprints.help',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->add('disable_manufacturers', CheckboxType::class, [
            'required' => false,
            'label' => 'category.edit.disable_manufacturers',
            'help' => 'category.edit.disable_manufacturers.help',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->add('disable_autodatasheets', CheckboxType::class, [
            'required' => false,
            'label' => 'category.edit.disable_autodatasheets',
            'help' => 'category.edit.disable_autodatasheets.help',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->add('disable_properties', CheckboxType::class, [
            'required' => false,
            'label' => 'category.edit.disable_properties',
            'help' => 'category.edit.disable_properties.help',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->add('partname_hint', TextType::class, [
            'required' => false,
            'empty_data' => '',
            'label' => 'category.edit.partname_hint',
            'attr' => [
                'placeholder' => 'category.edit.partname_hint.placeholder',
            ],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->add('partname_regex', TextType::class, [
            'required' => false,
            'empty_data' => '',
            'label' => 'category.edit.partname_regex',
            'help' => 'category.edit.partname_regex.help',
            'attr' => [
                'placeholder' => 'category.edit.partname_regex.placeholder',
            ],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->add('default_description', RichTextEditorType::class, [
            'required' => false,
            'empty_data' => '',
            'label' => 'category.edit.default_description',
            'mode' => 'markdown-single_line',
            'attr' => [
                'placeholder' => 'category.edit.default_description.placeholder',
            ],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->add('default_comment', RichTextEditorType::class, [
            'required' => false,
            'empty_data' => '',
            'label' => 'category.edit.default_comment',
            'mode' => 'markdown-full',
            'attr' => [
                'placeholder' => 'category.edit.default_comment.placeholder',
            ],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        //EDA info
        $builder->add('eda_info', EDACategoryInfoType::class, [
            'label' => false,
            'required' => false,
        ]);
    }
}
