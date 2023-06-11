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

namespace App\Services\LogSystem;

/**
 * This service is used to check if a log change comment is needed for a given operation type.
 * It is configured using the "enforce_change_comments_for" config parameter.
 */
class EventCommentNeededHelper
{
    final public const VALID_OPERATION_TYPES = [
        'part_edit',
        'part_create',
        'part_delete',
        'part_stock_operation',
        'datastructure_edit',
        'datastructure_create',
        'datastructure_delete',
    ];

    public function __construct(protected array $enforce_change_comments_for)
    {
    }

    /**
     * Checks if a log change comment is needed for the given operation type
     */
    public function isCommentNeeded(string $comment_type): bool
    {
        //Check if the comment type is valid
        if (! in_array($comment_type, self::VALID_OPERATION_TYPES, true)) {
            throw new \InvalidArgumentException('The comment type "'.$comment_type.'" is not valid!');
        }

        return in_array($comment_type, $this->enforce_change_comments_for, true);
    }
}