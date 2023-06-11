<?php
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

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\UserSystem\User;
use App\Repository\UserRepository;
use App\Services\UserSystem\PermissionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * The purpose of this class is, to use the anonymous user from DB in the case, that nobody is logged in.
 */
abstract class ExtendedVoter extends Voter
{
    public function __construct(protected PermissionManager $resolver, protected EntityManagerInterface $entityManager)
    {
    }

    final protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        //An allowed user is not allowed to do anything...
        if ($user instanceof User && $user->isDisabled()) {
            return false;
        }

        // if the user is anonymous (meaning $user is null), we use the anonymous user.
        if (!$user instanceof User) {
            /** @var UserRepository $repo */
            $repo = $this->entityManager->getRepository(User::class);
            $user = $repo->getAnonymousUser();
            if (!$user instanceof User) {
                return false;
            }
        }

        return $this->voteOnUser($attribute, $subject, $user);
    }

    /**
     * Similar to voteOnAttribute, but checking for the anonymous user is already done.
     * The current user (or the anonymous user) is passed by $user.
     */
    abstract protected function voteOnUser(string $attribute, $subject, User $user): bool;
}
