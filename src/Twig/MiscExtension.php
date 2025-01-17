<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Twig;

use Symfony\Component\HttpFoundation\Request;
use App\Services\LogSystem\EventCommentType;
use Jbtronics\SettingsBundle\Proxy\SettingsProxyInterface;
use ReflectionClass;
use Twig\TwigFunction;
use App\Services\LogSystem\EventCommentNeededHelper;
use Twig\Extension\AbstractExtension;

final class MiscExtension extends AbstractExtension
{
    public function __construct(private readonly EventCommentNeededHelper $eventCommentNeededHelper)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('event_comment_needed', $this->evenCommentNeeded(...)),

            new TwigFunction('settings_icon', $this->settingsIcon(...)),
            new TwigFunction('uri_without_host', $this->uri_without_host(...))
        ];
    }

    private function evenCommentNeeded(string|EventCommentType $operation_type): bool
    {
        if (is_string($operation_type)) {
            $operation_type = EventCommentType::from($operation_type);
        }

        return $this->eventCommentNeededHelper->isCommentNeeded($operation_type);
    }

    /**
     * Returns the value of the icon attribute of the SettingsIcon attribute of the given class.
     * If the class does not have a SettingsIcon attribute, then null is returned.
     * @param  string|object  $objectOrClass
     * @return string|null
     * @throws \ReflectionException
     */
    private function settingsIcon(string|object $objectOrClass): ?string
    {
        //If the given object is a proxy, then get the real object
        if (is_a($objectOrClass, SettingsProxyInterface::class)) {
            $objectOrClass = get_parent_class($objectOrClass);
        }

        $reflection = new ReflectionClass($objectOrClass);

        $attribute = $reflection->getAttributes(\App\Settings\SettingsIcon::class)[0] ?? null;

        return $attribute?->newInstance()->icon;
    }

    /**
     * Similar to the getUri function of the request, but does not contain protocol and host.
     * @param  Request  $request
     * @return string
     */
    public function uri_without_host(Request $request): string
    {
        if (null !== $qs = $request->getQueryString()) {
            $qs = '?'.$qs;
        }

        return $request->getBaseUrl().$request->getPathInfo().$qs;
    }
}
