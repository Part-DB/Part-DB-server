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


namespace App\Form\Type;

use App\Entity\Attachments\AttachmentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type to select the AttachmentType to use in an attachment form. This is used to filter the available attachment types based on the target class.
 */
class AttachmentTypeType extends AbstractType
{
    public function getParent(): ?string
    {
        return StructuralEntityType::class;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->define('attachment_filter_class')->allowedTypes('null', 'string')->default(null);

        $resolver->setDefault('class', AttachmentType::class);

        $resolver->setDefault('choice_filter', function (Options $options) {
            if (is_a($options['class'], AttachmentType::class, true) && $options['attachment_filter_class'] !== null) {
                return static function (?AttachmentType $choice) use ($options) {
                    return $choice?->isAllowedForTarget($options['attachment_filter_class']);
                };
            }
            return null;
        });
    }
}
