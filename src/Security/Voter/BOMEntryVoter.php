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

use App\Entity\ProjectSystem\ProjectBOMEntry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class BOMEntryVoter extends Voter
{

    private const ALLOWED_ATTRIBUTES = ['read', 'view', 'edit', 'delete', 'create'];

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->supportsAttribute($attribute) && is_a($subject, ProjectBOMEntry::class);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$subject instanceof ProjectBOMEntry) {
            return false;
        }

        $project = $subject->getProject();

        //Allow everything if the project was not set yet
        if ($project === null) {
            return true;
        }

        //Entry can be read if the user has read access to the project
        if ($attribute === 'read') {
            return $this->security->isGranted('read', $project);
        }

        //Everything else can be done if the user has edit access to the project
        return $this->security->isGranted('edit', $project);
    }

    public function supportsAttribute(string $attribute): bool
    {
        return in_array($attribute, self::ALLOWED_ATTRIBUTES, true);
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, ProjectBOMEntry::class, true);
    }
}