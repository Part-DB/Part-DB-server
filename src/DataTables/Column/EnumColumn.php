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

namespace App\DataTables\Column;

use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UnitEnum;

/**
 * @template T of UnitEnum
 */
class EnumColumn extends AbstractColumn
{

    /**
     * @phpstan-return T
     */
    public function normalize($value): UnitEnum
    {
        if (is_a($value, $this->getEnumClass())) {
            return $value;
        }

        //@phpstan-ignore-next-line
        return ($this->getEnumClass())::from($value);
    }

    protected function configureOptions(OptionsResolver $resolver): static
    {
        parent::configureOptions($resolver);

        $resolver->setRequired('class');
        $resolver->setAllowedTypes('class', 'string');
        $resolver->addAllowedValues('class', enum_exists(...));

        return $this;
    }

    /**
     * @return class-string<T>
     */
    public function getEnumClass(): string
    {
        return $this->options['class'];
    }
}
