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

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Services;

use App\Entity\Base\AbstractStructuralDBElement;
use Doctrine\ORM\EntityManagerInterface;

class StructuralElementRecursionHelper
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Executes an function (callable) recursivly for $element and every of its children.
     *
     * @param AbstractStructuralDBElement $element          The element on which the func should be executed
     * @param callable                    $func             The function which should be executed for each element.
     *                                                      $func has the signature function(StructuralDBElement $element) : void
     * @param int                         $max_depth        The maximum depth for which should be recursivly called. So if this is set to 5, after the
     *                                                      5th level the execution is stopped.
     * @param bool                        $call_from_bottom If set to true the bottom elements (elements with high level) will be called first.
     *                                                      Set to false if you want to call the top elements first.
     */
    public function execute(AbstractStructuralDBElement $element, callable $func, int $max_depth = -1, $call_from_bottom = true): void
    {
        //Cancel if we reached our maximal allowed level. Must be zero because -1 is infinity levels
        if (0 === $max_depth) {
            return;
        }

        //Get children of the current class:
        $children = $element->getChildren();

        //If we should call from top we execute the func here.
        if (!$call_from_bottom) {
            $func($element);
        }

        foreach ($children as $child) {
            $this->execute($child, $func, $max_depth - 1);
        }

        //Otherwise we call it here
        if ($call_from_bottom) {
            $func($element);
        }
    }

    /**
     * Deletes the $element and all its subelements recursivly.
     *
     * @param AbstractStructuralDBElement $element the element which should be deleted
     * @param bool                        $flush   When set to true the changes will also be flushed to DB. Set to false if you want to flush
     *                                             later.
     */
    public function delete(AbstractStructuralDBElement $element, bool $flush = true): void
    {
        $em = $this->em;

        $this->execute($element, static function (AbstractStructuralDBElement $element) use ($em): void {
            $em->remove($element);
        });

        if ($flush) {
            $em->flush();
        }
    }
}
