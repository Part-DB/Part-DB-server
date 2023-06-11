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

namespace App\Form\Filters;

use App\DataTables\Filters\AttachmentFilter;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\AttachmentTypeAttachment;
use App\Entity\Attachments\CategoryAttachment;
use App\Entity\Attachments\CurrencyAttachment;
use App\Entity\Attachments\ProjectAttachment;
use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Attachments\GroupAttachment;
use App\Entity\Attachments\LabelAttachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Attachments\StorelocationAttachment;
use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Attachments\UserAttachment;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Form\Filters\Constraints\BooleanConstraintType;
use App\Form\Filters\Constraints\DateTimeConstraintType;
use App\Form\Filters\Constraints\InstanceOfConstraintType;
use App\Form\Filters\Constraints\NumberConstraintType;
use App\Form\Filters\Constraints\StructuralEntityConstraintType;
use App\Form\Filters\Constraints\TextConstraintType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttachmentFilterType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => AttachmentFilter::class,
            'csrf_protection' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('dbId', NumberConstraintType::class, [
            'label' => 'part.filter.dbId',
            'min' => 1,
            'step' => 1,
        ]);

        $builder->add('name', TextConstraintType::class, [
            'label' => 'attachment.edit.name',
        ]);

        $builder->add('targetType', InstanceOfConstraintType::class, [
            'label' => 'attachment.table.element_type',
            'choices' => [
                'part.label' => PartAttachment::class,
                'attachment_type.label' => AttachmentTypeAttachment::class,
                'category.label' => CategoryAttachment::class,
                'currency.label' => CurrencyAttachment::class,
                'project.label' => ProjectAttachment::class,
                'footprint.label' => FootprintAttachment::class,
                'group.label' => GroupAttachment::class,
                'label_profile.label' => LabelAttachment::class,
                'manufacturer.label' => Manufacturer::class,
                'measurement_unit.label' => MeasurementUnit::class,
                'storelocation.label' => StorelocationAttachment::class,
                'supplier.label' => SupplierAttachment::class,
                'user.label' => UserAttachment::class,
            ]
        ]);

        $builder->add('attachmentType', StructuralEntityConstraintType::class, [
            'label' => 'attachment.attachment_type',
            'entity_class' => AttachmentType::class
        ]);

        $builder->add('showInTable', BooleanConstraintType::class, [
            'label' => 'attachment.edit.show_in_table'
        ]);

        $builder->add('lastModified', DateTimeConstraintType::class, [
            'label' => 'lastModified'
        ]);

        $builder->add('addedDate', DateTimeConstraintType::class, [
            'label' => 'createdAt'
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'filter.submit',
        ]);

        $builder->add('discard', ResetType::class, [
            'label' => 'filter.discard',
        ]);
    }
}