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

namespace App\Services\LabelSystem\PlaceholderProviders;

use App\Entity\Base\AbstractStructuralDBElement;

final class StructuralDBElementProvider implements PlaceholderProviderInterface
{
    public function replace(string $placeholder, object $label_target, array $options = []): ?string
    {
        if ($label_target instanceof AbstractStructuralDBElement) {
            if ('[[COMMENT]]' === $placeholder) {
                return $label_target->getComment();
            }
            if ('[[COMMENT_T]]' === $placeholder) {
                return strip_tags($label_target->getComment());
            }
            if ('[[FULL_PATH]]' === $placeholder) {
                return $label_target->getFullPath();
            }
            if ('[[PARENT]]' === $placeholder) {
                return $label_target->getParent() ? $label_target->getParent()->getName() : '';
            }
            if ('[[PARENT_FULL_PATH]]' === $placeholder) {
                return $label_target->getParent() ? $label_target->getParent()->getFullPath() : '';
            }
        }

        return null;
    }
}
