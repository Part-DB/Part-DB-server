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

namespace App\Services\LabelSystem\PlaceholderProviders;

use App\Entity\Parts\Storelocation;

class StorelocationProvider implements PlaceholderProviderInterface
{
    public function replace(string $placeholder, object $label_target, array $options = []): ?string
    {
        if ($label_target instanceof Storelocation) {
            if ('[[OWNER]]' === $placeholder) {
                return $label_target->getOwner() instanceof \App\Entity\UserSystem\User ? $label_target->getOwner()->getFullName() : '';
            }

            if ('[[OWNER_USERNAME]]' === $placeholder) {
                return $label_target->getOwner() instanceof \App\Entity\UserSystem\User ? $label_target->getOwner()->getUsername() : '';
            }
        }

        return null;
    }
}