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

namespace App\Twig;

use App\Entity\LogSystem\AbstractLogEntry;
use App\Repository\LogEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UserExtension extends AbstractExtension
{
    /** @var LogEntryRepository */
    private $repo;

    public function __construct(EntityManagerInterface $em)
    {
        $this->repo = $em->getRepository(AbstractLogEntry::class);
    }

    public function getFunctions(): array
    {
        return [
            /* Returns the user which has edited the given entity the last time. */
            new TwigFunction('last_editing_user', [$this->repo, 'getLastEditingUser']),
            /* Returns the user which has created the given entity. */
            new TwigFunction('creating_user', [$this->repo, 'getCreatingUser']),
        ];
    }
}
