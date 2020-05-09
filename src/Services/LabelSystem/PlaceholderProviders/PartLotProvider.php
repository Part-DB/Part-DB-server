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


use App\Entity\Parts\PartLot;
use App\Services\AmountFormatter;
use App\Services\LabelSystem\LabelTextReplacer;
use IntlDateFormatter;
use Locale;

final class PartLotProvider implements PlaceholderProviderInterface
{
    private $labelTextReplacer;
    private $amountFormatter;

    public function __construct(LabelTextReplacer $labelTextReplacer, AmountFormatter $amountFormatter)
    {
        $this->labelTextReplacer = $labelTextReplacer;
        $this->amountFormatter = $amountFormatter;
    }

    public function replace(string $placeholder, object $label_target, array $options = []): ?string
    {
        if ($label_target instanceof PartLot) {
            if ($placeholder === '[[LOT_ID]]') {
                return $label_target->getID() ?? 'unknown';
            }

            if ($placeholder === '[[LOT_NAME]]') {
                return $label_target->getName();
            }

            if ($placeholder === '[[LOT_COMMENT]]') {
                return $label_target->getComment();
            }

            if ($placeholder === '[[EXPIRATION_DATE]]') {
                if ($label_target->getExpirationDate() === null) {
                    return '';
                }
                $formatter = IntlDateFormatter::create(
                    Locale::getDefault(),
                    IntlDateFormatter::SHORT,
                    IntlDateFormatter::NONE
                //$label_target->getExpirationDate()->getTimezone()
                );

                return $formatter->format($label_target->getExpirationDate());
            }

            if ($placeholder === '[[AMOUNT]]') {
                if ($label_target->isInstockUnknown()) {
                    return '?';
                }
                return $this->amountFormatter->format($label_target->getAmount(), $label_target->getPart()->getPartUnit());
            }

            if ($placeholder === '[[LOCATION]]') {
                return $label_target->getStorageLocation() ? $label_target->getStorageLocation()->getName() : '';
            }

            if ($placeholder === '[[LOCATION_FULL]]') {
                return $label_target->getStorageLocation() ? $label_target->getStorageLocation()->getFullPath() : '';
            }


            return $this->labelTextReplacer->handlePlaceholder($placeholder, $label_target->getPart());
        }

        return null;
    }
}