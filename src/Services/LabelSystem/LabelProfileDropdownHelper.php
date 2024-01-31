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

namespace App\Services\LabelSystem;

use App\Entity\LabelSystem\LabelProfile;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\Repository\LabelProfileRepository;
use App\Services\Cache\ElementCacheTagGenerator;
use App\Services\Cache\UserCacheKeyGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class LabelProfileDropdownHelper
{
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserCacheKeyGenerator $keyGenerator,
        private readonly ElementCacheTagGenerator $tagGenerator,
    ) {
    }

    /**
     * Return all label profiles for the given supported element type
     * @param  LabelSupportedElement|string  $type
     * @return array
     */
    public function getDropdownProfiles(LabelSupportedElement|string $type): array
    {
        //Useful for the twig templates, where we use the string representation of the enum
        if (is_string($type)) {
            $type = LabelSupportedElement::from($type);
        }

        $secure_class_name = $this->tagGenerator->getElementTypeCacheTag(LabelProfile::class);
        $key = 'profile_dropdown_'.$this->keyGenerator->generateKey().'_'.$secure_class_name.'_'.$type->value;

        /** @var LabelProfileRepository $repo */
        $repo = $this->entityManager->getRepository(LabelProfile::class);

        return $this->cache->get($key, function (ItemInterface $item) use ($repo, $type, $secure_class_name) {
            // Invalidate when groups, an element with the class or the user changes
            $item->tag(['groups', 'tree_treeview', $this->keyGenerator->generateKey(), $secure_class_name]);

            return $repo->getDropdownProfiles($type);
        });
    }
}
