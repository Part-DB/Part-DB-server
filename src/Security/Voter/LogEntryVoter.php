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

use App\Services\UserSystem\VoterHelper;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\UserSystem\User;
use App\Services\UserSystem\PermissionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @phpstan-extends Voter<non-empty-string, AbstractLogEntry>
 */
final class LogEntryVoter extends Voter
{
    final public const ALLOWED_OPS = ['read', 'show_details', 'delete'];

    public function __construct(private readonly Security $security, private readonly VoterHelper $helper)
    {
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $this->helper->resolveUser($token);

        if (!$subject instanceof AbstractLogEntry) {
            throw new \InvalidArgumentException('The subject must be an instance of '.AbstractLogEntry::class);
        }

        if ('delete' === $attribute) {
            return $this->helper->isGranted($token, 'system', 'delete_logs');
        }

        if ('read' === $attribute) {
            //Allow read of the users own log entries
            if (
                $subject->getUser() === $user
                && $this->helper->isGranted($token, 'self', 'show_logs')
            ) {
                return true;
            }

            return $this->helper->isGranted($token, 'system', 'show_logs');
        }

        if ('show_details' === $attribute) {
            //To view details of a element related log entry, the user needs to be able to view the history of this entity type
            $targetClass = $subject->getTargetClass();
            if (null !== $targetClass) {
                return $this->security->isGranted('show_history', $targetClass);
            }

            //In other cases, this behaves like the read permission
            return $this->voteOnAttribute('read', $subject, $token);
        }

        return false;
    }

    protected function supports($attribute, $subject): bool
    {
        if ($subject instanceof AbstractLogEntry) {
            return in_array($attribute, static::ALLOWED_OPS, true);
        }

        return false;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return in_array($attribute, static::ALLOWED_OPS, true);
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AbstractLogEntry::class, true);
    }
}
