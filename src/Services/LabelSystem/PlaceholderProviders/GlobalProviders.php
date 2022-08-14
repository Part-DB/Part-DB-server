<?php

declare(strict_types=1);

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

namespace App\Services\LabelSystem\PlaceholderProviders;

use App\Entity\UserSystem\User;
use DateTime;
use IntlDateFormatter;
use Locale;
use Symfony\Component\Security\Core\Security;

/**
 * Provides Placeholders for infos about global infos like Installation name or datetimes.
 */
final class GlobalProviders implements PlaceholderProviderInterface
{
    private $partdb_title;
    private $security;

    public function __construct(string $partdb_title, Security $security)
    {
        $this->partdb_title = $partdb_title;
        $this->security = $security;
    }

    public function replace(string $placeholder, object $label_target, array $options = []): ?string
    {
        if ('[[INSTALL_NAME]]' === $placeholder) {
            return $this->partdb_title;
        }

        $user = $this->security->getUser();
        if ('[[USERNAME]]' === $placeholder) {
            if ($user instanceof User) {
                return $user->getName();
            }

            return 'anonymous';
        }

        if ('[[USERNAME_FULL]]' === $placeholder) {
            if ($user instanceof User) {
                return $user->getFullName(true);
            }

            return 'anonymous';
        }

        $now = new DateTime();

        if ('[[DATETIME]]' === $placeholder) {
            $formatter = IntlDateFormatter::create(
                Locale::getDefault(),
                IntlDateFormatter::SHORT,
                IntlDateFormatter::SHORT,
                $now->getTimezone()
            );

            return $formatter->format($now);
        }

        if ('[[DATE]]' === $placeholder) {
            $formatter = IntlDateFormatter::create(
                Locale::getDefault(),
                IntlDateFormatter::SHORT,
                IntlDateFormatter::NONE,
                $now->getTimezone()
            );

            return $formatter->format($now);
        }

        if ('[[TIME]]' === $placeholder) {
            $formatter = IntlDateFormatter::create(
                Locale::getDefault(),
                IntlDateFormatter::NONE,
                IntlDateFormatter::SHORT,
                $now->getTimezone()
            );

            return $formatter->format($now);
        }

        return null;
    }
}
