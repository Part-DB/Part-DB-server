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
namespace App\Exceptions;

use Doctrine\DBAL\Exception\DriverException;
use ErrorException;

class InvalidRegexException extends \RuntimeException
{
    public function __construct(private readonly ?string $reason = null)
    {
        parent::__construct('Invalid regular expression');
    }

    /**
     * Returns the reason for the exception (what the regex driver deemed invalid)
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * Creates a new exception from a driver exception happening, when MySQL encounters an invalid regex
     */
    public static function fromDriverException(DriverException $exception): self
    {
        //1139 means invalid regex error
        if ($exception->getCode() !== 1139) {
            throw new \InvalidArgumentException('The given exception is not a driver exception', 0, $exception);
        }

        //Reason is the part after the erorr code
        $reason = preg_replace('/^.*1139 /', '', $exception->getMessage());

        return new self($reason);
    }

    /**
     * Creates a new exception from the errorException thrown by mb_ereg
     */
    public static function fromMBRegexError(ErrorException $ex): self
    {
        //Ensure that the error is really a mb_ereg error
        if ($ex->getSeverity() !== E_WARNING || !strpos($ex->getMessage(), 'mb_ereg()')) {
            throw new \InvalidArgumentException('The given exception is not a mb_ereg error', 0, $ex);
        }

        //Reason is the part after the erorr code
        $reason = preg_replace('/^.*mb_ereg\(\): /', '', $ex->getMessage());

        return new self($reason);
    }
}
