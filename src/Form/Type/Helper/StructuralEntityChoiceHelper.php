<?php
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

use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Contracts\HasMasterAttachmentInterface;
use App\Entity\PriceInformations\Currency;
use App\Entity\UserSystem\User;
use App\Form\Type\MasterPictureAttachmentType;
use App\Services\Attachments\AttachmentURLGenerator;
use RuntimeException;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Contracts\Translation\TranslatorInterface;

class StructuralEntityChoiceHelper
{

    private AttachmentURLGenerator $attachmentURLGenerator;
    private TranslatorInterface $translator;

    public function __construct(AttachmentURLGenerator $attachmentURLGenerator, TranslatorInterface $translator)
    {
        $this->attachmentURLGenerator = $attachmentURLGenerator;
        $this->translator = $translator;
    }

    /**
     * Generates the choice attributes for the given AbstractStructuralDBElement.
     * @param  AbstractNamedDBElement  $choice
     * @param Options|array $options
     * @return array|string[]
     */
    public function generateChoiceAttr(AbstractNamedDBElement $choice, $options): array
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
            if (null !== $parent) {
                $level -= $parent->getLevel() - 1;
            }

            $tmp += [
                'data-level' => $level,
                'data-parent' => $choice->getParent() ? $choice->getParent()->getFullPath() : null,
                'data-path' => $choice->getFullPath('->'),
            ];
        }

        if ($choice instanceof HasMasterAttachmentInterface) {
            $tmp['data-image'] = $choice->getMasterPictureAttachment() ?
                $this->attachmentURLGenerator->getThumbnailURL($choice->getMasterPictureAttachment(),
                    'thumbnail_xs')
                : null
            ;
        }

        if ($choice instanceof AttachmentType && !empty($choice->getFiletypeFilter())) {
            $tmp += ['data-filetype_filter' => $choice->getFiletypeFilter()];
        }

        return $tmp;
    }

    /**
     * Generates the choice attributes for the given AbstractStructuralDBElement.
     * @param  Currency $choice
     * @param Options|array $options
     * @return array|string[]
     */
    public function generateChoiceAttrCurrency(Currency $choice, $options): array
    {
        $tmp = $this->generateChoiceAttr($choice, $options);

        if(!empty($choice->getIsoCode())) {
            $symbol = Currencies::getSymbol($choice->getIsoCode());
        } else {
            $symbol = null;
        }

        if ($options['short']) {
            $tmp['data-short'] = $symbol;
        } else {
            $tmp['data-short'] = $choice->getName();
        }

        $tmp += [
            'data-symbol' => $symbol,
        ];

        return $tmp;
    }

    /**
     * Returns the choice label for the given AbstractStructuralDBElement.
     * @param  AbstractNamedDBElement  $choice
     * @return string
     */
    public function generateChoiceLabel(AbstractNamedDBElement $choice): string
    {
        return $choice->getName();
    }

    /**
     * Returns the choice value for the given AbstractStructuralDBElement.
     * @param  AbstractNamedDBElement|null  $element
     * @return string|int|null
     */
    public function generateChoiceValue(?AbstractNamedDBElement $element)
    {
        if ($element === null) {
            return null;
        }

        /**
         * Do not change the structure below, even when inspection says it can be replaced with a null coalescing operator.
         * It is important that the value returned here for a existing element is an int, and for a new element a string.
         * I dont really understand why, but it seems to be important for the choice_loader to work correctly.
         * So please do not change this!
         */
        if ($element->getID() === null) {
            if ($element instanceof AbstractStructuralDBElement) {
                //Must be the same as the separator in the choice_loader, otherwise this will not work!
                return $element->getFullPath('->');
            }
            return $element->getName();
        }

        return $element->getID();
    }

    /**
     * @param  AbstractDBElement  $element
     * @return string|null
     */
    public function generateGroupBy(AbstractDBElement $element): ?string
    {
        //Show entities that are not added to DB yet separately from other entities
        if ($element->getID() === null) {
            return $this->translator->trans('entity.select.group.new_not_added_to_DB');
        }

        return null;
    }
}