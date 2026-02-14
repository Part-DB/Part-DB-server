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

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Attachments\ProjectAttachment;
use App\Services\ElementTypeNameGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Base\AbstractNamedDBElement;
use App\Services\Attachments\FileTypeFilterTools;
use App\Services\LogSystem\EventCommentNeededHelper;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Translation\StaticMessage;

class AttachmentTypeAdminForm extends BaseEntityAdminForm
{
    public function __construct(Security $security, protected FileTypeFilterTools $filterTools, EventCommentNeededHelper $eventCommentNeededHelper, private readonly ElementTypeNameGenerator $elementTypeNameGenerator)
    {
        parent::__construct($security, $eventCommentNeededHelper);
    }

    protected function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        $is_new = null === $entity->getID();


        $choiceLabel = function (string $class) {
            if (!is_a($class, Attachment::class, true)) {
                return $class;
            }
            return new StaticMessage($this->elementTypeNameGenerator->typeLabelPlural($class::ALLOWED_ELEMENT_CLASS));
        };


        $builder->add('allowed_targets', ChoiceType::class, [
            'required' => false,
            'choices' => array_values(Attachment::ORM_DISCRIMINATOR_MAP),
            'choice_label' => $choiceLabel,
            'preferred_choices' => [PartAttachment::class, ProjectAttachment::class],
            'label' => 'attachment_type.edit.allowed_targets',
            'help' => 'attachment_type.edit.allowed_targets.help',
            'multiple' => true,
        ]);

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
