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

namespace App\Services\AdminPages\Extension;

use App\Entity\Attachments\AttachmentType;
use App\Services\AdminPages\AbstractAdminPageExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

final class AttachmentTypeAdminExtension extends AbstractAdminPageExtension
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function checkDelete(object $entity): bool
    {
        if (!$entity instanceof AttachmentType) {
            throw new \InvalidArgumentException('Expected AttachmentType, got ' . $entity::class . '.');
        }

        if ($entity->getAttachmentsForType()->count() > 0) {
            /** @var Session $session */
            $session = $this->requestStack->getSession();
            $session->getFlashBag()->add('error', 'entity.delete.must_not_contain_attachments');

            return false;
        }

        return true;
    }
}
