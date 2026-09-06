<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Form\ProjectSystem;

use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Services\LogSystem\EventCommentNeededHelper;
use App\Services\LogSystem\EventCommentType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * An standalone form to edit a single BOM entry.
 */
class BOMEntryEditType extends AbstractType
{
    public function __construct(private readonly EventCommentNeededHelper $eventCommentNeededHelper)
    {

    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('bom_entry', ProjectBOMEntryType::class, [
                'label' => false,
                'constraints' => [
                    new UniqueEntity(fields: ['part', 'project'], message: 'project.bom_entry.part_already_in_bom', entityClass: ProjectBOMEntry::class),
                    new UniqueEntity(fields: ['name', 'project'], message: 'project.bom_entry.name_already_in_bom', entityClass: ProjectBOMEntry::class, ignoreNull: true),
                ],
            ])

            ->add('log_comment', TextType::class, [
                'label' => 'edit.log_comment',
                'required' => $this->eventCommentNeededHelper->isCommentNeeded(EventCommentType::DATASTRUCTURE_EDIT),
                'empty_data' => null,
            ])

            ->add('save', SubmitType::class, [
                'label' => 'entity.edit.save',
            ])

            ->add('reset', ResetType::class, [
                'label' => 'entity.edit.reset',
            ]);
    }
}
