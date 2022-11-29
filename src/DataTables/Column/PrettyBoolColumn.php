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

use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Contracts\Translation\TranslatorInterface;

class PrettyBoolColumn extends AbstractColumn
{
    protected TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function normalize($value): ?bool
    {
        if (null === $value) {
            return null;
        }

        return (bool) $value;
    }

    public function render($value, $context)
    {
        if ($value === true) {
            return '<span class="badge bg-success"><i class="fa-solid fa-circle-check fa-fw"></i> '
                . $this->translator->trans('bool.true')
                . '</span>';
        }

        if ($value === false) {
            return '<span class="badge bg-danger"><i class="fa-solid fa-circle-xmark fa-fw"></i> '
                . $this->translator->trans('bool.false')
                . '</span>';
        }

        if ($value === null) {
            return '<span class="badge bg-secondary>"<i class="fa-solid fa-circle-question fa-fw"></i> '
                . $this->translator->trans('bool.unknown')
                . '</span>';
        }

        throw new \RuntimeException('Unexpected value!');
    }
}