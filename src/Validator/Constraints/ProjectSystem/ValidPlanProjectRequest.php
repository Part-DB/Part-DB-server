<?php

declare(strict_types=1);

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
namespace App\Validator\Constraints\ProjectSystem;

use Symfony\Component\Validator\Constraint;

/**
 * This constraint checks that the given PlanProjectRequest only contains physically possible reservation
 * amounts: never negative, never more than a lot has available, and never more than a BOM entry actually needs.
 * Reserving less than a BOM entry needs is explicitly allowed (partial reservation) - if there isn't enough
 * available stock, whatever is available gets reserved and the rest shows up as a shortfall on the plan.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidPlanProjectRequest extends Constraint
{
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
