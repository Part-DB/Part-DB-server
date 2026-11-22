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
use App\Services\Attachments\FileTypeFilterTools;
use App\Services\ElementTypeNameGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Translation\StaticMessage;

class AttachmentTypeAdminForm extends AbstractType
{
    public function __construct(
        private readonly Security $security,
        private readonly FileTypeFilterTools $filterTools,
        private readonly ElementTypeNameGenerator $elementTypeNameGenerator,
    ) {
    }

    public function getParent(): string
    {
        return BaseEntityAdminForm::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $entity = $options['data'];
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
                'data-controller' => 'elements--tagsinput',
            ],
            'empty_data' => '',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->get('filetype_filter')->addViewTransformer(new CallbackTransformer(
            static fn ($value) => $value,
            fn ($value) => $this->filterTools->normalizeFilterString($value)
        ));
    }
}
