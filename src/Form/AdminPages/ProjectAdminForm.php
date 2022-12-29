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

namespace App\Form\AdminPages;

use App\Entity\Base\AbstractNamedDBElement;
use App\Form\ProjectSystem\ProjectBOMEntryCollectionType;
use App\Form\ProjectSystem\ProjectBOMEntryType;
use App\Form\Type\RichTextEditorType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

class ProjectAdminForm extends BaseEntityAdminForm
{
    protected function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        $builder->add('description', RichTextEditorType::class, [
            'required' => false,
            'label' => 'part.edit.description',
            'mode' => 'markdown-single_line',
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'part.edit.description.placeholder',
                'rows' => 2,
            ],
        ]);

        $builder->add('bom_entries', ProjectBOMEntryCollectionType::class);

        $builder->add('status', ChoiceType::class, [
            'attr' => [
                'class' => 'form-select',
            ],
            'label' => 'project.edit.status',
            'required' => false,
            'empty_data' => '',
            'choices' => [
                'project.status.draft' => 'draft',
                'project.status.planning' => 'planning',
                'project.status.in_production' => 'in_production',
                'project.status.finished' => 'finished',
                'project.status.archived' => 'archived',
            ],
        ]);
    }
}