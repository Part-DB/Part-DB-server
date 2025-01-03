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


namespace App\Validator\Constraints;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class Year2038BugWorkaroundValidator extends ConstraintValidator
{

    public function __construct(
        #[Autowire(env: "DISABLE_YEAR2038_BUG_CHECK")]
        private readonly bool $disable_validation = false
    )
    {
    }

    public function isActivated(): bool
    {
        //If we are on a 32 bit system and the validation is not disabled, we should activate the validation
        return !$this->disable_validation && PHP_INT_SIZE === 4;
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$this->isActivated()) {
            return;
        }

        //If the value is null, we don't need to validate it
        if ($value === null) {
            return;
        }

        //Ensure that we check the correct constraint
        if (!$constraint instanceof Year2038BugWorkaround) {
            throw new \InvalidArgumentException('This validator can only validate Year2038Bug constraints');
        }

        //We can only validate DateTime objects
        if (!$value instanceof \DateTimeInterface) {
            throw new UnexpectedTypeException($value, \DateTimeInterface::class);
        }

        //If we reach here the validation is active and we should forbid any date after 2038.
        if ($value->diff(new \DateTime('2038-01-19 03:14:06'))->invert === 1) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}