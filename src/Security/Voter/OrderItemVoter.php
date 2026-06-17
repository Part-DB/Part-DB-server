<?php

declare(strict_types=1);

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

namespace App\Security\Voter;

use App\Entity\OrderSystem\OrderItem;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Delegates OrderItem permissions to the parent Order voter.
 *
 * @phpstan-extends Voter<non-empty-string, OrderItem|class-string>
 */
final class OrderItemVoter extends Voter
{
    private const ALLOWED_ATTRIBUTES = ['read', 'edit', 'delete', 'create'];

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ALLOWED_ATTRIBUTES, true)
            && is_a($subject, OrderItem::class, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!is_object($subject)) {
            // Class-level check — fall back to order permission
            return $this->security->isGranted('@orders.' . $attribute);
        }

        $order = $subject->getOrder();
        if ($order === null) {
            return true;
        }

        if ($attribute === 'read') {
            return $this->security->isGranted('read', $order);
        }

        return $this->security->isGranted('edit', $order);
    }

    public function supportsAttribute(string $attribute): bool
    {
        return in_array($attribute, self::ALLOWED_ATTRIBUTES, true);
    }

    public function supportsType(string $subjectType): bool
    {
        return $subjectType === 'string' || is_a($subjectType, OrderItem::class, true);
    }
}
