<?php
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

use App\Entity\Base\NamedDBElement;
use App\Services\Attachments\FileTypeFilterTools;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class AttachmentTypeAdminForm extends BaseEntityAdminForm
{
    protected $filterTools;

    public function __construct(Security $security, ParameterBagInterface $params, FileTypeFilterTools $filterTools)
    {
        $this->filterTools = $filterTools;
        parent::__construct($security, $params);
    }

    protected function additionalFormElements(FormBuilderInterface $builder, array $options, NamedDBElement $entity)
    {
        $is_new = null === $entity->getID();

        $builder->add('filetype_filter', TextType::class, ['required' => false,
            'label' => 'attachment_type.edit.filetype_filter',
            'help' => 'attachment_type.edit.filetype_filter.help',
            'attr' => ['placeholder' => 'attachment_type.edit.filetype_filter.placeholder'],
            'empty_data' => '',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity), ]);

        //Normalize data before writing it to database
        $builder->get('filetype_filter')->addViewTransformer(new CallbackTransformer(
            function ($value) {
                return $value;
            },
            function ($value) {
                return $this->filterTools->normalizeFilterString($value);
            }
        ));
    }
}
