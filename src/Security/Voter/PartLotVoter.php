<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\Parts\PartLot;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\UserSystem\User;
use App\Services\PermissionResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class PartLotVoter extends ExtendedVoter
{
    protected Security $security;

    public function __construct(PermissionResolver $resolver, EntityManagerInterface $entityManager, Security $security)
    {
        parent::__construct($resolver, $entityManager);
        $this->security = $security;
    }

    protected const ALLOWED_PERMS = ['read', 'edit', 'create', 'delete', 'show_history', 'revert_element'];

    protected function voteOnUser(string $attribute, $subject, User $user): bool
    {
        if (! is_a($subject, PartLot::class, true)) {
            throw new \RuntimeException('This voter can only handle PartLot objects!');
        }

        switch ($attribute) {
            case 'read':
                $operation = 'read';
                break;
            case 'edit': //As long as we can edit, we can also edit orderdetails
            case 'create':
            case 'delete':
                $operation = 'edit';
                break;
            case 'show_history':
                $operation = 'show_history';
                break;
            case 'revert_element':
                $operation = 'revert_element';
                break;
            default:
                throw new \RuntimeException('Encountered unknown operation "'.$attribute.'"!');
        }

        //If we have no part associated use the generic part permission
        if (is_string($subject) || $subject->getPart() === null) {
            return $this->resolver->inherit($user, 'parts', $operation) ?? false;
        }

        //Otherwise vote on the part
        return $this->security->isGranted($attribute, $subject->getPart());
    }

    protected function supports($attribute, $subject): bool
    {
        if (is_a($subject, PartLot::class, true)) {
            return in_array($attribute, self::ALLOWED_PERMS, true);
        }

        return false;
    }
}
