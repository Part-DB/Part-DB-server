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

namespace App\DataTables\Column;

use App\Services\LogSystem\LogTargetHelper;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LogEntryTargetColumn extends AbstractColumn
{
    public function __construct(private readonly LogTargetHelper $logTargetHelper)
    {
    }

    /**
     * @param $value
     * @return mixed
     */
    public function normalize($value): mixed
    {
        return $value;
    }

    public function configureOptions(OptionsResolver $resolver): static
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('show_associated', true);
        $resolver->setDefault('showAccessDeniedPath', true);

        return $this;
    }

    public function render($value, $context): string
    {
        return $this->logTargetHelper->formatTarget($context, [
            'showAccessDeniedPath' => $this->options['showAccessDeniedPath'],
            'show_associated' => $this->options['show_associated'],
        ]);
    }
}
