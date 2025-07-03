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
namespace App\Form\Filters;

use App\DataTables\Filters\AssemblyFilter;
use App\Entity\Attachments\AttachmentType;
use App\Form\Filters\Constraints\DateTimeConstraintType;
use App\Form\Filters\Constraints\NumberConstraintType;
use App\Form\Filters\Constraints\StructuralEntityConstraintType;
use App\Form\Filters\Constraints\TextConstraintType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AssemblyFilterType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => AssemblyFilter::class,
            'csrf_protection' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /*
         * Common tab
         */

        $builder->add('name', TextConstraintType::class, [
            'label' => 'assembly.filter.name',
        ]);

        $builder->add('description', TextConstraintType::class, [
            'label' => 'assembly.filter.description',
        ]);

        $builder->add('comment', TextConstraintType::class, [
            'label' => 'assembly.filter.comment'
        ]);

        /*
         * Advanced tab
         */

        $builder->add('dbId', NumberConstraintType::class, [
            'label' => 'assembly.filter.dbId',
            'min' => 1,
            'step' => 1,
        ]);

        $builder->add('ipn', TextConstraintType::class, [
            'label' => 'assembly.filter.ipn',
        ]);

        $builder->add('lastModified', DateTimeConstraintType::class, [
            'label' => 'lastModified'
        ]);

        $builder->add('addedDate', DateTimeConstraintType::class, [
            'label' => 'createdAt'
        ]);

        /**
         * Attachments count
         */
        $builder->add('attachmentsCount', NumberConstraintType::class, [
            'label' => 'assembly.filter.attachments_count',
            'step' => 1,
            'min' => 0,
        ]);

        $builder->add('attachmentType', StructuralEntityConstraintType::class, [
            'label' => 'attachment.attachment_type',
            'entity_class' => AttachmentType::class
        ]);

        $builder->add('attachmentName', TextConstraintType::class, [
            'label' => 'assembly.filter.attachmentName',
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'filter.submit',
        ]);

        $builder->add('discard', ResetType::class, [
            'label' => 'filter.discard',
        ]);
    }
}
