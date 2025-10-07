<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\LabelSystem\PlaceholderProviders;

use App\Entity\Parts\Category;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Part;
use App\Services\Formatters\SIFormatter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\InlinesOnly\InlinesOnlyExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see \App\Tests\Services\LabelSystem\PlaceholderProviders\PartProviderTest
 */
final class PartProvider implements PlaceholderProviderInterface
{
    private readonly MarkdownConverter $inlineConverter;

    public function __construct(private readonly SIFormatter $siFormatter, private readonly TranslatorInterface $translator)
    {
        $environment = new Environment();
        $environment->addExtension(new InlinesOnlyExtension());
        $this->inlineConverter = new MarkdownConverter($environment);
    }

    public function replace(string $placeholder, object $part, array $options = []): ?string
    {
        if (!$part instanceof Part) {
            return null;
        }

        if ('[[CATEGORY]]' === $placeholder) {
            return $part->getCategory() instanceof Category ? $part->getCategory()->getName() : '';
        }

        if ('[[CATEGORY_FULL]]' === $placeholder) {
            return $part->getCategory() instanceof Category ? $part->getCategory()->getFullPath() : '';
        }

        if ('[[MANUFACTURER]]' === $placeholder) {
            return $part->getManufacturer() instanceof Manufacturer ? $part->getManufacturer()->getName() : '';
        }

        if ('[[MANUFACTURER_FULL]]' === $placeholder) {
            return $part->getManufacturer() instanceof Manufacturer ? $part->getManufacturer()->getFullPath() : '';
        }

        if ('[[FOOTPRINT]]' === $placeholder) {
            return $part->getFootprint() instanceof Footprint ? $part->getFootprint()->getName() : '';
        }

        if ('[[FOOTPRINT_FULL]]' === $placeholder) {
            return $part->getFootprint() instanceof Footprint ? $part->getFootprint()->getFullPath() : '';
        }

        if ('[[MASS]]' === $placeholder) {
            return $part->getMass() ? $this->siFormatter->format($part->getMass(), 'g', 1) : '';
        }

        if ('[[MPN]]' === $placeholder) {
            return $part->getManufacturerProductNumber();
        }

        if ('[[IPN]]' === $placeholder) {
            return $part->getIpn() ?? '';
        }

        if ('[[TAGS]]' === $placeholder) {
            return $part->getTags();
        }

        if ('[[M_STATUS]]' === $placeholder) {
            if (null === $part->getManufacturingStatus()) {
                return '';
            }

            return $this->translator->trans($part->getManufacturingStatus()->toTranslationKey());
        }

        if ('[[DESCRIPTION]]' === $placeholder) {
            return trim($this->inlineConverter->convert($part->getDescription())->getContent());
        }

        if ('[[DESCRIPTION_T]]' === $placeholder) {
            return trim(strip_tags($this->inlineConverter->convert($part->getDescription())->getContent()));
        }

        if ('[[COMMENT]]' === $placeholder) {
            return trim($this->inlineConverter->convert($part->getComment())->getContent());
        }

        if ('[[COMMENT_T]]' === $placeholder) {
            return trim(strip_tags($this->inlineConverter->convert($part->getComment())->getContent()));
        }

        return null;
    }
}
