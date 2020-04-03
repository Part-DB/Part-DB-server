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

declare(strict_types=1);

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

namespace App\Services\LogSystem;

use App\Entity\Contracts\LogWithCommentInterface;
use App\Entity\Contracts\LogWithEventUndoInterface;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\CollectionElementDeleted;
use App\Entity\LogSystem\DatabaseUpdatedLogEntry;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementDeletedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\LogSystem\ExceptionLogEntry;
use App\Entity\LogSystem\InstockChangedLogEntry;
use App\Entity\LogSystem\SecurityEventLogEntry;
use App\Entity\LogSystem\UserLoginLogEntry;
use App\Entity\LogSystem\UserLogoutLogEntry;
use App\Entity\LogSystem\UserNotAllowedLogEntry;
use App\Services\ElementTypeNameGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Format the Extra field of a log entry in a user readible form.
 */
class LogEntryExtraFormatter
{
    protected const CONSOLE_SEARCH = ['<i class="fas fa-long-arrow-alt-right"></i>', '<i>', '</i>', '<b>', '</b>'];
    protected const CONSOLE_REPLACE = ['→', '<info>', '</info>', '<error>', '</error>'];
    protected $translator;
    protected $elementTypeNameGenerator;

    public function __construct(TranslatorInterface $translator, ElementTypeNameGenerator $elementTypeNameGenerator)
    {
        $this->translator = $translator;
        $this->elementTypeNameGenerator = $elementTypeNameGenerator;
    }

    /**
     * Return an user viewable representation of the extra data in a log entry, styled for console output.
     *
     * @return string
     */
    public function formatConsole(AbstractLogEntry $logEntry): string
    {
        $arr = $this->getInternalFormat($logEntry);
        $tmp = [];

        //Make an array with entries in the form "<b>Key:</b> Value"
        foreach ($arr as $key => $value) {
            $str = '';
            if (is_string($key)) {
                $str .= '<error>'.$this->translator->trans($key).'</error>: ';
            }
            $str .= $value;
            if (! empty($str)) {
                $tmp[] = $str;
            }
        }

        return str_replace(static::CONSOLE_SEARCH, static::CONSOLE_REPLACE, implode('; ', $tmp));
    }

    /**
     * Return a HTML formatted string containing a user viewable form of the Extra data.
     *
     * @return string
     */
    public function format(AbstractLogEntry $context): string
    {
        $arr = $this->getInternalFormat($context);
        $tmp = [];

        //Make an array with entries in the form "<b>Key:</b> Value"
        foreach ($arr as $key => $value) {
            $str = '';
            if (is_string($key)) {
                $str .= '<b>'.$this->translator->trans($key).'</b>: ';
            }
            $str .= $value;
            if (! empty($str)) {
                $tmp[] = $str;
            }
        }

        return implode('; ', $tmp);
    }

    protected function getInternalFormat(AbstractLogEntry $context): array
    {
        $array = [];
        if ($context instanceof UserLoginLogEntry || $context instanceof UserLogoutLogEntry || $context instanceof SecurityEventLogEntry) {
            $array['log.user_login.ip'] = htmlspecialchars($context->getIPAddress());
        }

        if ($context instanceof ExceptionLogEntry) {
            $array[] = sprintf(
                '<i>%s</i> %s:%d : %s',
                htmlspecialchars($context->getExceptionClass()),
                htmlspecialchars($context->getFile()),
                $context->getLine(),
                htmlspecialchars($context->getMessage())
            );
        }

        if ($context instanceof DatabaseUpdatedLogEntry) {
            $array[] = sprintf(
                '<i>%s</i> %s <i class="fas fa-long-arrow-alt-right"></i> %s',
                $this->translator->trans($context->isSuccessful() ? 'log.database_updated.success' : 'log.database_updated.failure'),
                $context->getOldVersion(),
                $context->getNewVersion()
            );
        }

        if ($context instanceof LogWithEventUndoInterface) {
            if ($context->isUndoEvent()) {
                if ('undo' === $context->getUndoMode()) {
                    $array['log.undo_mode.undo'] = (string) $context->getUndoEventID();
                } elseif ('revert' === $context->getUndoMode()) {
                    $array['log.undo_mode.revert'] = (string) $context->getUndoEventID();
                }
            }
        }

        if ($context instanceof LogWithCommentInterface && $context->hasComment()) {
            $array[] = htmlspecialchars($context->getComment());
        }

        if ($context instanceof ElementCreatedLogEntry && $context->hasCreationInstockValue()) {
            $array['log.element_created.original_instock'] = (string) $context->getCreationInstockValue();
        }

        if ($context instanceof ElementDeletedLogEntry) {
            if (null !== $context->getOldName()) {
                $array['log.element_deleted.old_name'] = htmlspecialchars($context->getOldName());
            } else {
                $array['log.element_deleted.old_name'] = $this->translator->trans('log.element_deleted.old_name.unknown');
            }
        }

        if ($context instanceof ElementEditedLogEntry && $context->hasChangedFieldsInfo()) {
            $array['log.element_edited.changed_fields'] = htmlspecialchars(implode(', ', $context->getChangedFields()));
        }

        if ($context instanceof InstockChangedLogEntry) {
            $array[] = $this->translator->trans($context->isWithdrawal() ? 'log.instock_changed.withdrawal' : 'log.instock_changed.added');
            $array[] = sprintf(
                '%s <i class="fas fa-long-arrow-alt-right"></i> %s (%s)',
                $context->getOldInstock(),
                $context->getNewInstock(),
                (! $context->isWithdrawal() ? '+' : '-').$context->getDifference(true)
            );
            $array['log.instock_changed.comment'] = htmlspecialchars($context->getComment());
        }

        if ($context instanceof CollectionElementDeleted) {
            $array['log.collection_deleted.deleted'] = sprintf(
                '%s: %s (%s)',
                $this->elementTypeNameGenerator->getLocalizedTypeLabel($context->getDeletedElementClass()),
                $context->getOldName() ?? $context->getDeletedElementID(),
                $context->getCollectionName()
            );
        }

        if ($context instanceof UserNotAllowedLogEntry) {
            $array[] = htmlspecialchars($context->getMessage());
        }

        return $array;
    }
}
