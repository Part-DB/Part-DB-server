<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Security\Voter;

use App\Services\UserSystem\VoterHelper;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\UserSystem\User;
use App\Services\UserSystem\PermissionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @phpstan-extends Voter<non-empty-string, Orderdetail|class-string>
 */
final class OrderdetailVoter extends Voter
{
    public function __construct(private readonly Security $security, private readonly VoterHelper $helper)
    {
    }

    protected const ALLOWED_PERMS = ['read', 'edit', 'create', 'delete', 'show_history', 'revert_element'];

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if (! is_a($subject, Orderdetail::class, true)) {
            throw new \RuntimeException('This voter can only handle Orderdetail objects!');
        }

        $operation = match ($attribute) {
            'read' => 'read',
            'edit', 'create', 'delete' => 'edit',
            'show_history' => 'show_history',
            'revert_element' => 'revert_element',
            default => throw new \RuntimeException('Encountered unknown operation "'.$attribute.'"!'),
        };

        //If we have no part associated use the generic part permission
        if (is_string($subject) || !$subject->getPart() instanceof Part) {
            return $this->helper->isGranted($token, 'parts', $operation);
        }

        //Otherwise vote on the part
        return $this->security->isGranted($attribute, $subject->getPart());
    }

    protected function supports($attribute, $subject): bool
    {
        if (is_a($subject, Orderdetail::class, true)) {
            return in_array($attribute, self::ALLOWED_PERMS, true);
        }

        return false;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return in_array($attribute, self::ALLOWED_PERMS, true);
    }

    public function supportsType(string $subjectType): bool
    {
        return $subjectType === 'string' || is_a($subjectType, Orderdetail::class, true);
    }
}
