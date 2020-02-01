<?php

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

namespace App\Security\Voter;

use App\Entity\UserSystem\User;
use App\Services\PermissionResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * The purpose of this class is, to use the anonymous user from DB in the case, that nobody is logged in.
 */
abstract class ExtendedVoter extends Voter
{
    protected $entityManager;
    /**
     * @var PermissionResolver
     */
    protected $resolver;

    public function __construct(PermissionResolver $resolver, EntityManagerInterface $entityManager)
    {
        $this->resolver = $resolver;
        $this->entityManager = $entityManager;
    }

    final protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        //An allowed user is not allowed to do anything...
        if ($user instanceof User && $user->isDisabled()) {
            return false;
        }

        // if the user is anonymous, we use the anonymous user.
        if (! $user instanceof User) {
            $repo = $this->entityManager->getRepository(User::class);
            $user = $repo->getAnonymousUser();
            if (null === $user) {
                return false;
            }
        }

        return $this->voteOnUser($attribute, $subject, $user);
    }

    /**
     * Similar to voteOnAttribute, but checking for the anonymous user is already done.
     * The current user (or the anonymous user) is passed by $user.
     *
     * @param string $attribute
     * @param mixed $subject
     * @return bool
     */
    abstract protected function voteOnUser($attribute, $subject, User $user): bool;
}
