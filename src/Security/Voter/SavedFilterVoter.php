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

namespace App\Security\Voter;

use App\Entity\UserSystem\SavedFilter;
use App\Services\UserSystem\VoterHelper;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Decides on the saved filters of the part lists:
 * Everybody, who can see parts, can open the shared filters. The own filters can be created, changed and deleted with
 * the "create" operation of the "saved_filters" permission. Shared filters can only be created, changed and deleted
 * with the "share" operation, regardless of who created them.
 * @phpstan-extends Voter<string, SavedFilter|class-string<SavedFilter>>
 */
final class SavedFilterVoter extends Voter
{
    private const ATTRIBUTES = ['read', 'create', 'edit', 'delete', 'share'];

    public function __construct(private readonly VoterHelper $helper)
    {
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $this->helper->resolveUser($token);

        if ($attribute === 'share') {
            return !$user->isAnonymousUser() && $this->helper->isGranted($token, 'saved_filters', 'share', $vote);
        }

        if (!$this->helper->isGranted($token, 'parts', 'read', $vote)) {
            return false;
        }

        //Creating a filter needs an owner, so the anonymous user can not save filters
        if (!$subject instanceof SavedFilter) {
            return $attribute === 'create' && !$user->isAnonymousUser()
                && $this->helper->isGranted($token, 'saved_filters', 'create', $vote);
        }

        $isOwner = $subject->getOwner() !== null && $subject->getOwner()->getID() === $user->getID();

        if ($attribute === 'read') {
            return $subject->isShared() || $isOwner;
        }

        //Changing or deleting a filter
        if ($subject->isShared()) {
            return !$user->isAnonymousUser() && $this->helper->isGranted($token, 'saved_filters', 'share', $vote);
        }

        return $isOwner && $this->helper->isGranted($token, 'saved_filters', 'create', $vote);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true)
            && ($subject instanceof SavedFilter || $subject === SavedFilter::class);
    }

    public function supportsAttribute(string $attribute): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true);
    }

    public function supportsType(string $subjectType): bool
    {
        return $subjectType === 'string' || is_a($subjectType, SavedFilter::class, true);
    }
}
