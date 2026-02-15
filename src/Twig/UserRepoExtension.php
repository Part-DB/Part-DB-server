<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Twig;

use App\Entity\Base\AbstractDBElement;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\UserSystem\User;
use App\Repository\LogEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class UserRepoExtension
{

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Returns the user which has edited the given entity the last time.
     */
    #[AsTwigFunction('creating_user')]
    public function creatingUser(AbstractDBElement $element): ?User
    {
        return $this->entityManager->getRepository(AbstractLogEntry::class)->getCreatingUser($element);
    }

    /**
     * Returns the user which has edited the given entity the last time.
     */
    #[AsTwigFunction('last_editing_user')]
    public function lastEditingUser(AbstractDBElement $element): ?User
    {
        return $this->entityManager->getRepository(AbstractLogEntry::class)->getLastEditingUser($element);
    }
}
