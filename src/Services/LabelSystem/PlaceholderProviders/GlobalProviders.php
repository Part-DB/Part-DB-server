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

namespace App\Services\LabelSystem\PlaceholderProviders;

use App\Settings\SystemSettings\CustomizationSettings;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\UserSystem\User;
use DateTime;
use IntlDateFormatter;
use Locale;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Provides Placeholders for infos about global infos like Installation name or datetimes.
 * @see \App\Tests\Services\LabelSystem\PlaceholderProviders\GlobalProvidersTest
 */
final class GlobalProviders implements PlaceholderProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $url_generator,
        private CustomizationSettings $customizationSettings,
    )
    {
    }

    public function replace(string $placeholder, object $label_target, array $options = []): ?string
    {
        if ('[[INSTALL_NAME]]' === $placeholder) {
            return $this->customizationSettings->instanceName;
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

        $now = new \DateTimeImmutable();

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

        if ('[[INSTANCE_URL]]' === $placeholder) {
            return $this->url_generator->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return null;
    }
}
