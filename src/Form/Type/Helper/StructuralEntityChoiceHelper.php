<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Form\Type\Helper;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Contracts\HasMasterAttachmentInterface;
use App\Entity\PriceInformations\Currency;
use App\Services\Attachments\AttachmentURLGenerator;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Contracts\Translation\TranslatorInterface;

class StructuralEntityChoiceHelper
{

    public function __construct(private readonly AttachmentURLGenerator $attachmentURLGenerator, private readonly TranslatorInterface $translator)
    {
    }

    /**
     * Generates the choice attributes for the given AbstractStructuralDBElement.
     * @return array<string, mixed>
     */
    public function generateChoiceAttr(AbstractNamedDBElement $choice, Options|array $options): array
    {
        $tmp = [
            'data-level' => 0,
            'data-path' => $choice->getName(),
        ];

        if ($choice instanceof AbstractStructuralDBElement) {
            //Disable attribute if the choice is marked as not selectable
            if (($options['disable_not_selectable'] ?? false) && $choice->isNotSelectable()) {
                $tmp += ['disabled' => 'disabled'];
            }

            if ($choice instanceof AttachmentType) {
                $tmp += ['data-filetype_filter' => $choice->getFiletypeFilter()];
            }

            $level = $choice->getLevel();
            /** @var AbstractStructuralDBElement|null $parent */
            $parent = $options['subentities_of'] ?? null;
            if ($parent instanceof AbstractStructuralDBElement) {
                $level -= $parent->getLevel() - 1;
            }

            $tmp['data-level'] = $level;
            $tmp['data-parent'] = $choice->getParent() instanceof AbstractStructuralDBElement ? $choice->getParent()->getFullPath() : null;
            $tmp['data-path'] = $choice->getFullPath('->');
        }

        if ($choice instanceof HasMasterAttachmentInterface) {
            $tmp['data-image'] = ($choice->getMasterPictureAttachment() instanceof Attachment
                && $choice->getMasterPictureAttachment()->isPicture()) ?
                $this->attachmentURLGenerator->getThumbnailURL($choice->getMasterPictureAttachment(),
                    'thumbnail_xs')
                : null
            ;
        }

        if ($choice instanceof AttachmentType && $choice->getFiletypeFilter() !== '') {
            $tmp += ['data-filetype_filter' => $choice->getFiletypeFilter()];
        }

        //Show entities that are not added to DB yet separately from other entities
        $tmp['data-not_in_db_yet'] = $choice->getID() === null;

        return $tmp;
    }

    /**
     * Generates the choice attributes for the given AbstractStructuralDBElement.
     * @return array|string[]
     */
    public function generateChoiceAttrCurrency(Currency $choice, Options|array $options): array
    {
        $tmp = $this->generateChoiceAttr($choice, $options);
        $symbol = $choice->getIsoCode() === '' ? null : Currencies::getSymbol($choice->getIsoCode());
        $tmp['data-short'] = $options['short'] ? $symbol : $choice->getName();

        //Show entities that are not added to DB yet separately from other entities
        $tmp['data-not_in_db_yet'] = $choice->getID() === null;

        return $tmp + [
            'data-symbol' => $symbol,
        ];
    }

    /**
     * Returns the choice label for the given AbstractStructuralDBElement.
     */
    public function generateChoiceLabel(AbstractNamedDBElement $choice): string
    {
        return $choice->getName();
    }

    /**
     * Returns the choice value for the given AbstractStructuralDBElement.
     */
    public function generateChoiceValue(?AbstractNamedDBElement $element): string|int|null
    {
        if (!$element instanceof AbstractNamedDBElement) {
            return null;
        }

        /**
         * Do not change the structure below, even when inspection says it can be replaced with a null coalescing operator.
         * It is important that the value returned here for an existing element is an int, and for a new element a string.
         * I don't really understand why, but it seems to be important for the choice_loader to work correctly.
         * So please do not change this!
         */
        if ($element->getID() === null) {
            if ($element instanceof AbstractStructuralDBElement) {
                //Must be the same as the separator in the choice_loader, otherwise this will not work!
                return '$%$' . $element->getFullPath('->');
            }
            // '$%$' is the indicator prefix for a new entity
            return '$%$' . $element->getName();
        }

        return $element->getID();
    }

    public function generateGroupBy(AbstractDBElement $element): ?string
    {
        //Show entities that are not added to DB yet separately from other entities
        if ($element->getID() === null) {
            return $this->translator->trans('entity.select.group.new_not_added_to_DB');
        }

        return null;
    }
}
