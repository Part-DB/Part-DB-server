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


namespace App\Security\Voter;

use App\Entity\UserSystem\User;
use App\Services\UserSystem\PermissionManager;
use App\Services\UserSystem\VoterHelper;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * This voter implements a virtual role, which can be used if the user has any permission set to allowed.
 * We use this to restrict access to the homepage.
 */
final class HasAccessPermissionsVoter extends Voter
{
    public const ROLE = "HAS_ACCESS_PERMISSIONS";

    public function __construct(private readonly PermissionManager $permissionManager, private readonly VoterHelper $helper)
    {
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $this->helper->resolveUser($token);
        return $this->permissionManager->hasAnyPermissionSetToAllowInherited($user);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ROLE;
    }
}