<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\LabelSystem\PlaceholderProviders;

use App\Entity\Parts\Part;
use App\Services\SIFormatter;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PartProvider implements PlaceholderProviderInterface
{
    private $siFormatter;
    private $translator;

    public function __construct(SIFormatter $SIFormatter, TranslatorInterface $translator)
    {
        $this->siFormatter = $SIFormatter;
        $this->translator = $translator;
    }

    public function replace(string $placeholder, object $part, array $options = []): ?string
    {
        if (!$part instanceof Part) {
            return null;
        }

        if ('[[CATEGORY]]' === $placeholder) {
            return $part->getCategory() ? $part->getCategory()->getName() : '';
        }

        if ('[[CATEGORY_FULL]]' === $placeholder) {
            return $part->getCategory() ? $part->getCategory()->getFullPath() : '';
        }

        if ('[[MANUFACTURER]]' === $placeholder) {
            return $part->getManufacturer() ? $part->getManufacturer()->getName() : '';
        }

        if ('[[MANUFACTURER_FULL]]' === $placeholder) {
            return $part->getManufacturer() ? $part->getManufacturer()->getFullPath() : '';
        }

        if ('[[FOOTPRINT]]' === $placeholder) {
            return $part->getFootprint() ? $part->getFootprint()->getName() : '';
        }

        if ('[[FOOTPRINT_FULL]]' === $placeholder) {
            return $part->getFootprint() ? $part->getFootprint()->getFullPath() : '';
        }

        if ('[[MASS]]' === $placeholder) {
            return $part->getMass() ? $this->siFormatter->format($part->getMass(), 'g', 1) : '';
        }

        if ('[[MPN]]' === $placeholder) {
            return $part->getManufacturerProductNumber();
        }

        if ('[[TAGS]]' === $placeholder) {
            return $part->getTags();
        }

        if ('[[M_STATUS]]' === $placeholder) {
            if ('' === $part->getManufacturingStatus()) {
                return '';
            }

            return $this->translator->trans('m_status.'.$part->getManufacturingStatus());
        }

        $parsedown = new \Parsedown();

        if ('[[DESCRIPTION]]' === $placeholder) {
            return $parsedown->line($part->getDescription());
        }

        if ('[[DESCRIPTION_T]]' === $placeholder) {
            return strip_tags($parsedown->line($part->getDescription()));
        }

        if ('[[COMMENT]]' === $placeholder) {
            return $parsedown->line($part->getComment());
        }

        if ('[[COMMENT_T]]' === $placeholder) {
            return strip_tags($parsedown->line($part->getComment()));
        }

        return null;
    }
}
