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
use App\Services\Attachments\FileTypeFilterTools;
use App\Services\LogSystem\EventCommentNeededHelper;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Security;

class AttachmentTypeAdminForm extends BaseEntityAdminForm
{
    public function __construct(\Symfony\Bundle\SecurityBundle\Security $security, protected FileTypeFilterTools $filterTools, EventCommentNeededHelper $eventCommentNeededHelper)
    {
        parent::__construct($security, $eventCommentNeededHelper);
    }

    protected function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        $is_new = null === $entity->getID();

        $builder->add('filetype_filter', TextType::class, [
            'required' => false,
            'label' => 'attachment_type.edit.filetype_filter',
            'help' => 'attachment_type.edit.filetype_filter.help',
            'attr' => [
                'placeholder' => 'attachment_type.edit.filetype_filter.placeholder',
                'data-controller' => 'elements--tagsinput'
            ],
            'empty_data' => '',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        //Normalize data before writing it to database
        $builder->get('filetype_filter')->addViewTransformer(new CallbackTransformer(
            static fn($value) => $value,
            fn($value) => $this->filterTools->normalizeFilterString($value)
        ));
    }
}
