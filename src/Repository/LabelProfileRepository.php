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

namespace App\Repository;

use App\Entity\LabelSystem\LabelOptions;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\Helpers\Trees\TreeViewNode;

/**
 * @template TEntityClass of LabelProfile
 * @extends NamedDBElementRepository<TEntityClass>
 */
class LabelProfileRepository extends NamedDBElementRepository
{
    /**
     * Find the profiles that are shown in the dropdown for the given type.
     * You should maybe use the cached version of this in LabelProfileDropdownHelper.
     */
    public function getDropdownProfiles(LabelSupportedElement $type): array
    {
        return $this->findBy([
            'options.supported_element' => $type,
            'show_in_dropdown' => true,
        ], ['name' => 'ASC']);
    }

    /**
     * Gets a tree of TreeViewNode elements. The root elements has $parent as parent.
     * The treeview is generic, that means the href are null and ID values are set.
     *
     * @return TreeViewNode[]
     */
    public function getGenericNodeTree(): array
    {
        $result = [];

        foreach (LabelSupportedElement::cases() as $type) {
            $type_children = [];
            $entities = $this->findForSupportedElement($type);
            foreach ($entities as $entity) {
                /** @var LabelProfile $entity */
                $node = new TreeViewNode($entity->getName(), null, null);
                $node->setId($entity->getID());
                $type_children[] = $node;
            }

            if ($type_children !== []) {
                //Use default label e.g. 'part_label'. $$ marks that it will be translated in TreeViewGenerator
                $tmp = new TreeViewNode('$$'.$type->value.'.label', null, $type_children);

                $result[] = $tmp;
            }
        }

        return $result;
    }

    /**
     * Find all LabelProfiles that can be used with the given type.
     *
     * @param LabelSupportedElement $type     see LabelOptions::SUPPORTED_ELEMENTS for valid values
     * @param array  $order_by The way the results should be sorted. By default ordered by
     */
    public function findForSupportedElement(LabelSupportedElement $type, array $order_by = ['name' => 'ASC']): array
    {
        return $this->findBy(['options.supported_element' => $type], $order_by);
    }

    /**
     * Returns all LabelProfiles that can be used for parts
     */
    public function getPartLabelProfiles(): array
    {
        return $this->getDropdownProfiles(LabelSupportedElement::PART);
    }

    /**
     * Returns all LabelProfiles that can be used for part lots
     */
    public function getPartLotsLabelProfiles(): array
    {
        return $this->getDropdownProfiles(LabelSupportedElement::PART_LOT);
    }

    /**
     * Returns all LabelProfiles that can be used for storelocations
     */
    public function getStorelocationsLabelProfiles(): array
    {
        return $this->getDropdownProfiles(LabelSupportedElement::STORELOCATION);
    }
}
