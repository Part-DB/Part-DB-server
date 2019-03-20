<?php
/**
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/.
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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

use App\Entity\User;
use App\Services\PermissionResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * The purpose of this class is, to use the anonymous user from DB in the case, that nobody is logged in.
 */
abstract class ExtendedVoter extends Voter
{
    /**
     * @var PermissionResolver
     */
    protected $resolver;

    protected $entityManager;

    public function __construct(PermissionResolver $resolver, EntityManagerInterface $entityManager)
    {
        $this->resolver = $resolver;
        $this->entityManager = $entityManager;
    }

    final protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        // if the user is anonymous, we use the anonymous user.
        if (!$user instanceof User) {
            $user = $this->entityManager->find(User::class, User::ID_ANONYMOUS);
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
     * @param $attribute
     * @param $subject
     * @param User $user
     *
     * @return bool
     */
    abstract protected function voteOnUser($attribute, $subject, User $user): bool;
}
