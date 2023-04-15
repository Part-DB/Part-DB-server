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

namespace App\DataTables\Column;

use App\Services\Formatters\SIFormatter;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SIUnitNumberColumn extends AbstractColumn
{
    protected SIFormatter $formatter;

    public function __construct(SIFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    public function configureOptions(OptionsResolver $resolver): self
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('precision', 2);
        $resolver->setDefault('unit', '');

        return $this;
    }

    public function normalize($value)
    {
        //Ignore null values
        if ($value === null) {
            return '';
        }

        return htmlspecialchars($this->formatter->format((float) $value, $this->options['unit'], $this->options['precision']));
    }
}