<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Validator\Constraints;

use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface;
use Symfony\Component\Validator\Constraint;

class ValidGoogleAuthCode extends Constraint
{
    /**
     * @param  TwoFactorInterface|null  $user The user to use for the validation process, if null, the current user is used
     */
    public function __construct(
        array $options = null,
        string $message = null,
        array $groups = null,
        public ?TwoFactorInterface $user = null)
    {
        parent::__construct($options, $message, $groups);
    }
}
