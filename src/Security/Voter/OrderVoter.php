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

use App\Entity\OrderSystem\Order;
use App\Services\UserSystem\VoterHelper;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A Voter that votes on Order entities.
 *
 * Valid operations: read, edit, create, delete, receive
 *
 * @phpstan-extends Voter<non-empty-string, Order|class-string>
 */
final class OrderVoter extends Voter
{
    public function __construct(private readonly VoterHelper $helper)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (is_a($subject, Order::class, true)) {
            return $this->helper->isValidOperation('orders', $attribute);
        }

        return false;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->helper->isGranted($token, 'orders', $attribute, $vote);
    }

    public function supportsAttribute(string $attribute): bool
    {
        return $this->helper->isValidOperation('orders', $attribute);
    }

    public function supportsType(string $subjectType): bool
    {
        return $subjectType === 'string' || is_a($subjectType, Order::class, true);
    }
}
