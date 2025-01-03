<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Services\UserSystem;

use App\Entity\UserSystem\User;
use App\Repository\UserRepository;
use App\Security\ApiTokenAuthenticatedToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @see \App\Tests\Services\UserSystem\VoterHelperTest
 */
final class VoterHelper
{
    private readonly UserRepository $userRepository;

    public function __construct(private readonly PermissionManager $permissionManager, private readonly EntityManagerInterface $entityManager)
    {
        $this->userRepository = $this->entityManager->getRepository(User::class);
    }

    /**
     * Checks if the operation on the given permission is granted for the given token.
     * Similar to isGrantedTrinary, but returns false if the permission is not granted.
     * @param  TokenInterface  $token  The token to check
     * @param  string  $permission  The permission to check
     * @param  string  $operation  The operation to check
     * @return bool
     */
    public function isGranted(TokenInterface $token, string $permission, string $operation): bool
    {
        return $this->isGrantedTrinary($token, $permission, $operation) ?? false;
    }

    /**
     * Checks if the operation on the given permission is granted for the given token.
     * The result is returned in trinary value, where null means inherted from the parent.
     * @param  TokenInterface  $token The token to check
     * @param  string  $permission The permission to check
     * @param  string  $operation The operation to check
     * @return bool|null The result of the check. Null means inherted from the parent.
     */
    public function isGrantedTrinary(TokenInterface $token, string $permission, string $operation): ?bool
    {
        $user = $token->getUser();

        if ($user instanceof User) {
            //A disallowed user is not allowed to do anything...
            if ($user->isDisabled()) {
                return false;
            }
        } else {
            //Try to resolve the user from the token
            $user = $this->resolveUser($token);
        }

        //If the token is a APITokenAuthenticated
        if ($token instanceof ApiTokenAuthenticatedToken) {
            //Use the special API token checker
            return $this->permissionManager->inheritWithAPILevel($user, $token->getRoleNames(), $permission, $operation);
        }

        //Otherwise use the normal permission checker
        return $this->permissionManager->inherit($user, $permission, $operation);
    }

    /**
     * Resolves the user from the given token. If the token is anonymous, the anonymous user is returned.
     * @return User
     */
    public function resolveUser(TokenInterface $token): User
    {
        $user = $token->getUser();
        //If the user is a User entity, just return it
        if ($user instanceof User) {
            return $user;
        }

        //If the user is null, return the anonymous user
        if ($user === null) {
            $user = $this->userRepository->getAnonymousUser();
            if (!$user instanceof User) {
                throw new \RuntimeException('The anonymous user could not be resolved.');
            }
            return $user;
        }

        //Otherwise throw an exception
        throw new \RuntimeException('The user could not be resolved.');
    }

    /**
     * Checks if the permission operation combination with the given names is existing.
     * Just a proxy to the permission manager.
     *
     * @param string $permission the name of the permission which should be checked
     * @param string $operation  the name of the operation which should be checked
     *
     * @return bool true if the given permission operation combination is existing
     */
    public function isValidOperation(string $permission, string $operation): bool
    {
        return $this->permissionManager->isValidOperation($permission, $operation);
    }
}