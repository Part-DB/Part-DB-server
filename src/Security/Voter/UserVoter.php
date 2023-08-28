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
use App\Services\UserSystem\PermissionManager;
use App\Services\UserSystem\VoterHelper;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

use function in_array;

final class UserVoter extends Voter
{
    public function __construct(private readonly VoterHelper $helper, private readonly PermissionManager $resolver)
    {
    }

    /**
     * Determines if the attribute and subject are supported by this voter.
     *
     * @param  string  $attribute An attribute
     * @param mixed  $subject   The subject to secure, e.g. an object the user wants to access or any other PHP type
     *
     * @return bool True if the attribute and subject are supported, false otherwise
     */
    protected function supports(string $attribute, $subject): bool
    {
        if (is_a($subject, User::class, true)) {
            return in_array($attribute,
                array_merge(
                    $this->resolver->listOperationsForPermission('users'),
                    $this->resolver->listOperationsForPermission('self'),
                    ['info']
                ),
                true
            );
        }

        return false;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return $this->helper->isValidOperation('users', $attribute) || $this->helper->isValidOperation('self', $attribute);
    }

    public function supportsType(string $subjectType): bool
    {
        return $subjectType === 'string' || is_a($subjectType, User::class, true);
    }

    /**
     * Similar to voteOnAttribute, but checking for the anonymous user is already done.
     * The current user (or the anonymous user) is passed by $user.
     *
     * @param  string  $attribute
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $this->helper->resolveUser($token);

        if ($attribute === 'info') {
            //Every logged-in user (non-anonymous) can see the info pages of other users
            if (!$user->isAnonymousUser()) {
                return true;
            }

            //For the anonymous user, use the user read permission
            $attribute = 'read';
        }

        //Check if the checked user is the user itself
        if (($subject instanceof User) && $subject->getID() === $user->getID() &&
            $this->helper->isValidOperation('self', $attribute)) {
            //Then we also need to check the self permission
            $tmp = $this->helper->isGranted($token, 'self', $attribute);
            //But if the self value is not allowed then use just the user value:
            if ($tmp) {
                return $tmp;
            }
        }

        //Else just check user permission:
        if ($this->helper->isValidOperation('users', $attribute)) {
            return $this->helper->isGranted($token, 'users', $attribute);
        }

        return false;
    }
}
