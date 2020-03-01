<?php
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

namespace App\DataTables\Column;


use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\CollectionElementDeleted;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementDeletedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Contracts\Translation\TranslatorInterface;

class RevertLogColumn extends AbstractColumn
{
    protected $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
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
        if ($context instanceof ElementDeletedLogEntry || $context instanceof CollectionElementDeleted) {
            $icon = 'fa-trash-restore';
            $title = $this->translator->trans('log.undo.undelete');
        } elseif ($context instanceof ElementEditedLogEntry || $context instanceof ElementCreatedLogEntry) {
            $icon = 'fa-undo';
            $title = $this->translator->trans('log.undo.undo');
        } else {
            return '';
        }


        return sprintf(
            '<button type="submit" class="btn btn-outline-secondary btn-sm" name="undo" value="%d"><i class="fas fa-fw %s" title="%s"></i></button>',
            $context->getID(),
            $icon,
            $title
        );
    }
}