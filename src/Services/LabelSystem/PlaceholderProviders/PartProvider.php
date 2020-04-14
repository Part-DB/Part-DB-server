<?php
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

class PartProvider implements PlaceholderProviderInterface
{

    protected $siFormatter;
    protected $translator;

    public function __construct(SIFormatter $SIFormatter, TranslatorInterface $translator)
    {
        $this->siFormatter = $SIFormatter;
        $this->translator = $translator;
    }

    /**
     * @inheritDoc
     */
    public function replace(string $placeholder, object $part, array $options = []): ?string
    {
        if (!$part instanceof Part) {
            return null;
        }

        if ($placeholder === '%%CATEGORY%%') {
            return $part->getCategory() ? $part->getCategory()->getName() : '';
        }

        if ($placeholder === '%%CATEGORY_FULL%%') {
            return $part->getCategory() ? $part->getCategory()->getFullPath() : '';
        }

        if ($placeholder === '%%MANUFACTURER%%') {
            return $part->getManufacturer() ? $part->getManufacturer()->getName() : '';
        }

        if ($placeholder === '%%MANUFACTURER_FULL%%') {
            return $part->getManufacturer() ? $part->getManufacturer()->getFullPath() : '';
        }

        if ($placeholder === '%%FOOTPRINT%%') {
            return $part->getFootprint() ? $part->getFootprint()->getName() : '';
        }

        if ($placeholder === '%%FOOTPRINT_FULL%%') {
            return $part->getFootprint() ? $part->getFootprint()->getFullPath() : '';
        }

        if ($placeholder === '%%MASS%%') {
            return $part->getMass() ? $this->siFormatter->format($part->getMass(), 'g', 1) : '';
        }

        if ($placeholder === '%%MPN%%') {
            return $part->getManufacturerProductNumber();
        }

        if ($placeholder === '%%TAGS%%') {
            return $part->getTags();
        }

        if ($placeholder === '%%M_STATUS%%') {
            if ($part->getManufacturingStatus() === '') {
                return '';
            }
            return $this->translator->trans('m_status.' . $part->getManufacturingStatus());
        }

        $parsedown = new \Parsedown();

        if ($placeholder === '%%DESCRIPTION%%') {
            return $parsedown->line($part->getDescription());
        }

        if ($placeholder === '%%DESCRIPTION_T%%') {
            return strip_tags($parsedown->line($part->getDescription()));
        }

        if ($placeholder === '%%COMMENT%%') {
            return $parsedown->line($part->getComment());
        }

        if ($placeholder === '%%COMMENT_T%%') {
            return strip_tags($parsedown->line($part->getComment()));
        }

        return null;
    }
}