<?php
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

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Security\Voter;

use App\Entity\Attachments\Attachment;
use App\Entity\UserSystem\User;
use App\Services\UserSystem\PermissionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

use function in_array;

class AttachmentVoter extends ExtendedVoter
{
    protected $security;

    public function __construct(PermissionManager $resolver, EntityManagerInterface $entityManager, Security $security)
    {
        parent::__construct($resolver, $entityManager);
        $this->security = $security;
    }

    /**
     * Similar to voteOnAttribute, but checking for the anonymous user is already done.
     * The current user (or the anonymous user) is passed by $user.
     *
     * @param  string  $attribute
     */
    protected function voteOnUser(string $attribute, $subject, User $user): bool
    {
        //return $this->resolver->inherit($user, 'attachments', $attribute) ?? false;

        //If the attachment has no element (which should not happen), we deny access, as we can not determine if the user is allowed to access the associated element
        $target_element = $subject->getElement();
        if (! $subject instanceof Attachment || null === $target_element) {
            return false;
        }

        //Depending on the operation delegate either to the attachments element or to the attachment permission
        switch ($attribute) {
            //We can view the attachment if we can view the element
            case 'read':
            case 'view':
                return $this->security->isGranted('read', $target_element);
            //We can edit/create/delete the attachment if we can edit the element
            case 'edit':
            case 'create':
            case 'delete':
                return $this->security->isGranted('edit', $target_element);

            case 'show_private':
                return $this->resolver->inherit($user, 'attachments', 'show_private') ?? false;
        }

        throw new \RuntimeException('Encountered unknown attribute "'.$attribute.'" in AttachmentVoter!');
    }

    /**
     * Determines if the attribute and subject are supported by this voter.
     *
     * @param  string  $attribute An attribute
     * @param mixed  $subject   The subject to secure, e.g. an object the user wants to access or any other PHP type
     *
     * @return bool True if the attribute and subject are supported, false otherwise
     */
    protected function supports(string $attribute, $subject): bool
    {
        if (is_a($subject, Attachment::class, true)) {
            //These are the allowed attributes
            return in_array($attribute, ['read', 'view', 'edit', 'delete', 'create', 'show_private'], true);
        }

        //Allow class name as subject
        return false;
    }
}
