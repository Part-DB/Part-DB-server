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

namespace App\Services\LogSystem;


use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Parts\Part;

class HistoryHelper
{
    public function __construct()
    {

    }

    /**
     * Returns an array containing all elements that are associated with the argument.
     * The returned array contains the given element.
     * @param  AbstractDBElement  $element
     * @return array
     */
    public function getAssociatedElements(AbstractDBElement $element): array
    {
        $array = [$element];
        if ($element instanceof AttachmentContainingDBElement) {
            $array = array_merge($array, $element->getAttachments()->toArray());
        }

        if ($element instanceof Part) {
            $array = array_merge(
                $array,
                $element->getPartLots()->toArray(),
                $element->getOrderdetails()->toArray()
            );
            foreach ($element->getOrderdetails() as $orderdetail) {
                $array = array_merge($array, $orderdetail->getPricedetails()->toArray());
            }
        }

        return $array;
    }
}