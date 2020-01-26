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

namespace App\Services\LogSystem;


use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\DatabaseUpdatedLogEntry;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementDeletedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\LogSystem\ExceptionLogEntry;
use App\Entity\LogSystem\InstockChangedLogEntry;
use App\Entity\LogSystem\UserLoginLogEntry;
use App\Entity\LogSystem\UserLogoutLogEntry;
use App\Entity\LogSystem\UserNotAllowedLogEntry;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Format the Extra field of a log entry in a user readible form.
 * @package App\Services\LogSystem
 */
class LogEntryExtraFormatter
{
    protected $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Return an user viewable representation of the extra data in a log entry, styled for console output.
     * @param  AbstractLogEntry  $logEntry
     * @return string
     */
    public function formatConsole(AbstractLogEntry $logEntry): string
    {
        $tmp = $this->format($logEntry);

        //Just a simple tweak to make the console output more pretty.
        $search = ['<i>', '</i>', '<b>', '</b>', ' <i class="fas fa-long-arrow-alt-right">'];
        $replace = ['<info>', '</info>', '<error>', '</error>', '→'];

        return str_replace($search, $replace, $tmp);
    }

    /**
     * Return a HTML formatted string containing a user viewable form of the Extra data
     * @param  AbstractLogEntry  $context
     * @return string
     */
    public function format(AbstractLogEntry $context): string
    {
        if ($context instanceof UserLoginLogEntry || $context instanceof UserLogoutLogEntry) {
            return sprintf(
                "<i>%s</i>: %s",
                $this->translator->trans('log.user_login.ip'),
                htmlspecialchars($context->getIPAddress())
            );
        }

        if ($context instanceof ExceptionLogEntry) {
            return sprintf(
                '<i>%s</i> %s:%d : %s',
                htmlspecialchars($context->getExceptionClass()),
                htmlspecialchars($context->getFile()),
                $context->getLine(),
                htmlspecialchars($context->getMessage())
            );
        }

        if ($context instanceof DatabaseUpdatedLogEntry) {
            return sprintf(
                '<i>%s</i> %s <i class="fas fa-long-arrow-alt-right"></i> %s',
                $this->translator->trans($context->isSuccessful() ? 'log.database_updated.success' : 'log.database_updated.failure'),
                $context->getOldVersion(),
                $context->getNewVersion()
            );
        }

        if ($context instanceof ElementCreatedLogEntry && $context->hasCreationInstockValue()) {
            return sprintf(
                '<i>%s</i>: %s',
                $this->translator->trans('log.element_created.original_instock'),
                $context->getCreationInstockValue()
            );
        }

        if ($context instanceof ElementDeletedLogEntry) {
            return sprintf(
                '<i>%s</i>: %s',
                $this->translator->trans('log.element_deleted.old_name'),
                $context->getOldName()
            );
        }

        if ($context instanceof ElementEditedLogEntry && !empty($context->getMessage())) {
            return htmlspecialchars($context->getMessage());
        }

        if ($context instanceof InstockChangedLogEntry) {
            return sprintf(
                '<i>%s</i>; %s <i class="fas fa-long-arrow-alt-right"></i> %s (%s); %s: %s',
                $this->translator->trans($context->isWithdrawal() ? 'log.instock_changed.withdrawal' : 'log.instock_changed.added'),
                $context->getOldInstock(),
                $context->getNewInstock(),
                (!$context->isWithdrawal() ? '+' : '-') . $context->getDifference(true),
                $this->translator->trans('log.instock_changed.comment'),
                htmlspecialchars($context->getComment())
            );
        }

        if ($context instanceof UserNotAllowedLogEntry) {
            return htmlspecialchars($context->getMessage());
        }

        return "";
    }
}