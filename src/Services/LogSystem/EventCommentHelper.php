<?php

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

namespace App\Services\LogSystem;

class EventCommentHelper
{
    protected const MAX_MESSAGE_LENGTH = 255;

    protected $message;

    public function __construct()
    {
        $message = null;
    }

    /**
     * Set the message that will be saved for all ElementEdited/Created/Deleted messages during the next flush.
     * Set to null if no message should be shown.
     * After the flush this message is cleared.
     */
    public function setMessage(?string $message): void
    {
        //Restrict the length of the string
        $this->message = mb_strimwidth($message, 0, self::MAX_MESSAGE_LENGTH, '...');
    }

    /**
     * Returns the currently set message, or null if no message is set yet.
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Clear the currently set message.
     */
    public function clearMessage(): void
    {
        $this->message = null;
    }

    /**
     * Check if a message is currently set.
     *
     * @return bool
     */
    public function isMessageSet(): bool
    {
        return is_string($this->message);
    }
}
