<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Services\LogSystem;

/**
 * This enum represents the different types of event comments that could be required, by the system.
 * They are almost only useful when working with the EventCommentNeededHelper service.
 */
enum EventCommentType: string
{
    case PART_EDIT = 'part_edit';
    case PART_CREATE = 'part_create';
    case PART_DELETE = 'part_delete';
    case PART_STOCK_OPERATION = 'part_stock_operation';
    case DATASTRUCTURE_EDIT = 'datastructure_edit';
    case DATASTRUCTURE_CREATE = 'datastructure_create';
    case DATASTRUCTURE_DELETE = 'datastructure_delete';
}
