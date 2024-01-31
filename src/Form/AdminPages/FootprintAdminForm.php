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
use App\Entity\EDA\EDAFootprintInfo;
use App\Form\Part\EDA\EDAFootprintInfoType;
use App\Form\Type\MasterPictureAttachmentType;
use Symfony\Component\Form\FormBuilderInterface;

class FootprintAdminForm extends BaseEntityAdminForm
{
    public function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        $builder->add('footprint_3d', MasterPictureAttachmentType::class, [
            'required' => false,
            'disabled' => !$this->security->isGranted(null === $entity->getID() ? 'create' : 'edit', $entity),
            'label' => 'footprint.edit.3d_model',
            'filter' => '3d_model',
            'entity' => $entity,
        ]);

        //EDA info
        $builder->add('eda_info', EDAFootprintInfoType::class, [
            'label' => false,
            'required' => false,
        ]);
    }
}
