<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\DataTables\Column;


use App\Entity\LogSystem\DatabaseUpdatedLogEntry;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementDeletedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\LogSystem\ExceptionLogEntry;
use App\Entity\LogSystem\InstockChangedLogEntry;
use App\Entity\LogSystem\UserLoginLogEntry;
use App\Entity\LogSystem\UserLogoutLogEntry;
use App\Entity\LogSystem\UserNotAllowedLogEntry;
use App\Services\LogSystem\LogEntryExtraFormatter;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Contracts\Translation\TranslatorInterface;

class LogEntryExtraColumn extends AbstractColumn
{
    protected $translator;
    protected $formatter;

    public function __construct(TranslatorInterface $translator, LogEntryExtraFormatter $formatter)
    {
        $this->translator = $translator;
        $this->formatter = $formatter;
    }

    /**
     * @inheritDoc
     */
    public function normalize($value)
    {
        return $value;
    }

    public function render($value, $context)
    {
        return $this->formatter->format($context);
    }
}