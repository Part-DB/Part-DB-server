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

namespace App\Twig;

use App\Entity\Base\AbstractDBElement;
use App\Entity\UserSystem\User;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Repository\LogEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class UserExtension extends AbstractExtension
{
    private readonly LogEntryRepository $repo;

    public function __construct(EntityManagerInterface $em)
    {
        $this->repo = $em->getRepository(AbstractLogEntry::class);
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('remove_locale_from_path', fn(string $path): string => $this->removeLocaleFromPath($path)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            /* Returns the user which has edited the given entity the last time. */
            new TwigFunction('last_editing_user', fn(AbstractDBElement $element): ?User => $this->repo->getLastEditingUser($element)),
            /* Returns the user which has created the given entity. */
            new TwigFunction('creating_user', fn(AbstractDBElement $element): ?User => $this->repo->getCreatingUser($element)),
        ];
    }

    /**
     * This function/filter generates a path.
     */
    public function removeLocaleFromPath(string $path): string
    {
        //Ensure the path has the correct format
        if (!preg_match('/^\/\w{2}\//', $path)) {
            throw new \InvalidArgumentException('The given path is not a localized path!');
        }

        $parts = explode('/', $path);
        //Remove the part with locale
        unset($parts[1]);

        return implode('/', $parts);
    }

}
