<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Form\AdminPages;

use App\Entity\Base\AbstractNamedDBElement;
use App\Form\Type\MasterPictureAttachmentType;
use Symfony\Component\Form\FormBuilderInterface;

class FootprintAdminForm extends BaseEntityAdminForm
{
    public function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        $builder->add('footprint_3d', MasterPictureAttachmentType::class, [
            'required' => false,
            'disabled' => ! $this->security->isGranted(null === $entity->getID() ? 'create' : 'edit', $entity),
            'label' => 'footprint.edit.3d_model',
            'filter' => '3d_model',
            'entity' => $entity,
        ]);
    }
}
