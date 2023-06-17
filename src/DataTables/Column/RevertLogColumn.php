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

declare(strict_types=1);

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

namespace App\DataTables\Column;

use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\LogSystem\CollectionElementDeleted;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementDeletedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Contracts\Translation\TranslatorInterface;

class RevertLogColumn extends AbstractColumn
{
    public function __construct(protected TranslatorInterface $translator, protected Security $security)
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

    public function render($value, $context): string
    {
        if (
            $context instanceof CollectionElementDeleted
            || ($context instanceof ElementDeletedLogEntry && $context->hasOldDataInformation())
        ) {
            $icon = 'fa-trash-restore';
            $title = $this->translator->trans('log.undo.undelete');
        } elseif (
            $context instanceof ElementCreatedLogEntry
            || ($context instanceof ElementEditedLogEntry && $context->hasOldDataInformation())
        ) {
            $icon = 'fa-undo';
            $title = $this->translator->trans('log.undo.undo');
        } else {
            return '';
        }

        $disabled = !$this->security->isGranted('revert_element', $context->getTargetClass());

        $tmp = '<div class="btn-group btn-group-sm">';
        $tmp .= sprintf(
            '<button type="submit" class="btn btn-outline-secondary" name="undo" value="%d" %s><i class="fas fa-fw %s" title="%s"></i></button>',
            $context->getID(),
            $disabled ? 'disabled' : '',
            $icon,
            $title
        );

        $tmp .= sprintf(
            '<button type="submit" class="btn btn-outline-secondary" name="revert" value="%d" %s><i class="fas fa-fw fa-backward" title="%s"></i></button>',
            $context->getID(),
            $disabled ? 'disabled' : '',
            $this->translator->trans('log.undo.revert')
        );

        return $tmp . '</div>';
    }
}
