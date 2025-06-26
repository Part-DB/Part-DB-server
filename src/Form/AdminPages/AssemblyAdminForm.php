<?php

declare(strict_types=1);

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
use App\Form\AssemblySystem\AssemblyBOMEntryCollectionType;
use App\Form\Type\RichTextEditorType;
use App\Services\LogSystem\EventCommentNeededHelper;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class AssemblyAdminForm extends BaseEntityAdminForm
{
    public function __construct(
        protected Security $security,
        protected EventCommentNeededHelper $eventCommentNeededHelper,
        protected bool $useAssemblyIpnPlaceholder = false
    ) {
        parent::__construct($security, $eventCommentNeededHelper, $useAssemblyIpnPlaceholder);
    }

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

        $builder->add('bom_entries', AssemblyBOMEntryCollectionType::class);

        $builder->add('status', ChoiceType::class, [
            'attr' => [
                'class' => 'form-select',
            ],
            'label' => 'assembly.edit.status',
            'required' => false,
            'empty_data' => '',
            'choices' => [
                'assembly.status.draft' => 'draft',
                'assembly.status.planning' => 'planning',
                'assembly.status.in_production' => 'in_production',
                'assembly.status.finished' => 'finished',
                'assembly.status.archived' => 'archived',
            ],
        ]);

        $builder->add('ipn', TextType::class, [
            'required' => false,
            'empty_data' => null,
            'label' => 'assembly.edit.ipn',
        ]);
    }
}
