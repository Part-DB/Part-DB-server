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

namespace App\Services\LabelSystem;

use App\Entity\LabelSystem\LabelProfile;
use App\Repository\LabelProfileRepository;
use App\Services\UserCacheKeyGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class LabelProfileDropdownHelper
{
    private TagAwareCacheInterface $cache;
    private EntityManagerInterface $entityManager;
    private UserCacheKeyGenerator $keyGenerator;

    public function __construct(TagAwareCacheInterface $treeCache, EntityManagerInterface $entityManager, UserCacheKeyGenerator $keyGenerator)
    {
        $this->cache = $treeCache;
        $this->entityManager = $entityManager;
        $this->keyGenerator = $keyGenerator;
    }

    public function getDropdownProfiles(string $type): array
    {
        $secure_class_name = str_replace('\\', '_', LabelProfile::class);
        $key = 'profile_dropdown_'.$this->keyGenerator->generateKey().'_'.$secure_class_name.'_'.$type;

        /** @var LabelProfileRepository $repo */
        $repo = $this->entityManager->getRepository(LabelProfile::class);

        return $this->cache->get($key, function (ItemInterface $item) use ($repo, $type, $secure_class_name) {
            // Invalidate when groups, a element with the class or the user changes
            $item->tag(['groups', 'tree_treeview', $this->keyGenerator->generateKey(), $secure_class_name]);

            return $repo->getDropdownProfiles($type);
        });
    }
}
