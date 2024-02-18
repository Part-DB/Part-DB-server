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
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * This voter implements a virtual role, which can be used if the user has any permission set to allowed.
 * We use this to restrict access to the homepage.
 * @phpstan-extends Voter<non-empty-string, User>
 */
final class ImpersonateUserVoter extends Voter
{

    public function __construct(private readonly VoterHelper $helper)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'CAN_SWITCH_USER'
            && $subject instanceof UserInterface;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return $this->helper->isGranted($token, 'users', 'impersonate');
    }

    public function supportsAttribute(string $attribute): bool
    {
        return $attribute === 'CAN_SWITCH_USER';
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, User::class, true);
    }
}