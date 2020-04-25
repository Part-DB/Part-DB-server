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

namespace App\Services\LabelSystem\PlaceholderProviders;


use App\Entity\UserSystem\User;
use IntlDateFormatter;
use Locale;
use Symfony\Component\Security\Core\Security;

/**
 * Provides Placeholders for infos about global infos like Installation name or datetimes.
 * @package App\Services\LabelSystem\PlaceholderProviders
 */
class GlobalProviders implements PlaceholderProviderInterface
{

    protected $partdb_title;
    protected $security;

    public function __construct(string $partdb_title, Security $security)
    {
        $this->partdb_title = $partdb_title;
        $this->security = $security;
    }

    /**
     * @inheritDoc
     */
    public function replace(string $placeholder, object $label_target, array $options = []): ?string
    {
        if ($placeholder === "[[INSTALL_NAME]]") {
            return $this->partdb_title;
        }


        $user = $this->security->getUser();
        if ($placeholder === "[[USERNAME]]") {
            if ($user instanceof User) {
                return $user->getName();
            }
            return 'anonymous';
        }

        if ($placeholder === "[[USERNAME_FULL]]") {
            if ($user instanceof User) {
                return $user->getFullName(true);
            }
            return 'anonymous';
        }

        $now = new \DateTime();

        if ($placeholder === '[[DATETIME]]') {
            $formatter = IntlDateFormatter::create(
                Locale::getDefault(),
                IntlDateFormatter::SHORT,
                IntlDateFormatter::SHORT,
                $now->getTimezone()
            );

            return $formatter->format($now);
        }

        if ($placeholder === '[[DATE]]') {
            $formatter = IntlDateFormatter::create(
                Locale::getDefault(),
                IntlDateFormatter::SHORT,
                IntlDateFormatter::NONE,
                $now->getTimezone()
            );

            return $formatter->format($now);
        }

        if ($placeholder === '[[TIME]]') {
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