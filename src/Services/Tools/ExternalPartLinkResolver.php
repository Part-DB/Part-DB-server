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

namespace App\Services\Tools;

use App\Entity\Parts\Part;
use App\Settings\ExternalPartLinkSettings;

/**
 * Resolves the administrator-configured external part link definitions (see
 * ExternalPartLinkSettings) into safe, ready-to-render links for a given part.
 *
 * A definition is only included in the result if it is enabled and its source
 * (a URL template or a part parameter) resolves to a complete, allowed URL.
 * This class never renders HTML - it only ever returns ResolvedExternalPartLink
 * view models, whose values are safe to pass through Twig's normal escaping.
 */
final class ExternalPartLinkResolver
{
    public function __construct(private readonly ExternalPartLinkSettings $settings)
    {
    }

    /**
     * @return ResolvedExternalPartLink[] In the order the links are configured in
     */
    public function resolve(Part $part): array
    {
        $result = [];

        foreach ($this->settings->links as $link) {
            $resolved = $this->resolveLink(is_array($link) ? $link : [], $part);
            if ($resolved !== null) {
                $result[] = $resolved;
            }
        }

        return $result;
    }

    private function resolveLink(array $link, Part $part): ?ResolvedExternalPartLink
    {
        if (!(bool) ($link['enabled'] ?? false)) {
            return null;
        }

        $name = trim((string) ($link['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $sourceType = ExternalPartLinkSourceType::tryFrom((string) ($link['source_type'] ?? ''));
        $source = (string) ($link['source'] ?? '');
        if ($sourceType === null || trim($source) === '') {
            return null;
        }

        $url = match ($sourceType) {
            ExternalPartLinkSourceType::TEMPLATE => $this->resolveTemplate($source, $part),
            ExternalPartLinkSourceType::PARAMETER => $this->resolveParameter($source, $part),
        };

        if ($url === null) {
            return null;
        }

        $icon = ExternalPartLinkIcon::tryFrom((string) ($link['icon'] ?? '')) ?? ExternalPartLinkIcon::EXTERNAL_LINK;
        $openNewTab = (bool) ($link['open_new_tab'] ?? true);

        return new ResolvedExternalPartLink($name, $url, $icon->toFontAwesomeClass(), $openNewTab);
    }

    /**
     * Substitutes the placeholders in $template with the part's values (see
     * ExternalPartLinkTemplateValidator::ALLOWED_PLACEHOLDERS), each percent-encoded as a single URL path
     * segment. Returns null (no link) if the template uses an unknown placeholder, or one of its placeholders
     * has no value on this part.
     */
    private function resolveTemplate(string $template, Part $part): ?string
    {
        $values = [
            'mpn' => $part->getManufacturerProductNumber(),
            'manufacturer' => $part->getManufacturer()?->getName(),
            'ipn' => $part->getIpn(),
            'name' => $part->getName(),
            'category' => $part->getCategory()?->getName(),
            'footprint' => $part->getFootprint()?->getName(),
            //A part which was not saved yet has no ID, so a link using it is simply not shown for it
            'id' => $part->getID() === null ? null : (string) $part->getID(),
        ];

        if (preg_match_all('/\{([a-zA-Z]+)}/', $template, $matches) > 0) {
            foreach (array_unique($matches[1]) as $placeholder) {
                if (!in_array($placeholder, ExternalPartLinkTemplateValidator::ALLOWED_PLACEHOLDERS, true)) {
                    return null;
                }
                $value = $values[$placeholder] ?? null;
                if ($value === null || $value === '') {
                    return null;
                }
            }
        }

        $url = preg_replace_callback(
            '/\{([a-zA-Z]+)}/',
            static fn (array $m): string => rawurlencode((string) $values[$m[1]]),
            $template
        );

        return $this->isAllowedUrl($url) ? $url : null;
    }

    /**
     * Looks up the exactly-one part parameter named $parameterName and uses its full value as
     * the (already complete) URL. Returns null if the parameter is missing, empty, ambiguous
     * (more than one parameter with that name), or its value is not an allowed URL.
     */
    private function resolveParameter(string $parameterName, Part $part): ?string
    {
        $parameterName = trim($parameterName);
        if ($parameterName === '') {
            return null;
        }

        $matches = [];
        foreach ($part->getParameters() as $parameter) {
            if ($parameter->getName() === $parameterName) {
                $matches[] = $parameter;
            }
        }

        if (count($matches) !== 1) {
            return null;
        }

        $value = trim((string) $matches[0]->getValueText());
        if ($value === '') {
            return null;
        }

        return $this->isAllowedUrl($value) ? $value : null;
    }

    /**
     * The only allowed scheme is https. This also rejects javascript:/data:/file: URLs and
     * relative paths, since none of those start with "https://".
     */
    private function isAllowedUrl(string $url): bool
    {
        return str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
