<?php

declare(strict_types=1);

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

namespace App\DataTables\Helpers;

use App\Entity\ProjectSystem\Project;
use App\Services\EntityURLGenerator;

/**
 * A helper service which contains common code to render columns for project related tables
 */
class ProjectDataTableHelper
{
    public function __construct(private readonly EntityURLGenerator $entityURLGenerator) {
    }

    public function renderName(Project $context): string
    {
        $icon = '';

        return sprintf(
            '<a href="%s">%s%s</a>',
            $this->entityURLGenerator->infoURL($context),
            $icon,
            htmlspecialchars($context->getName())
        );
    }
}
