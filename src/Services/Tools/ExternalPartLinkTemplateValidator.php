<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\Tools;

/**
 * Validates an external part link URL template (the "source" of a source_type=template link
 * definition), independently of any concrete part's placeholder values.
 *
 * This is the single source of truth for what makes a template acceptable, shared between the
 * admin settings form ({@see \App\Form\Settings\ExternalPartLinkRulesCollectionType}) and the
 * settings API, so both accept and reject the exact same values.
 */
final class ExternalPartLinkTemplateValidator
{
    /** @var string[] The only placeholders a URL template may use. Kept in sync with ExternalPartLinkResolver. */
    public const ALLOWED_PLACEHOLDERS = ['mpn', 'manufacturer', 'ipn', 'name', 'category', 'footprint', 'id'];

    /**
     * Returns the unknown placeholder name if $template uses one, or null if all placeholders
     * (if any) are allowed.
     */
    public static function findUnknownPlaceholder(string $template): ?string
    {
        if (preg_match_all('/\{([a-zA-Z]+)}/', $template, $matches) > 0) {
            foreach (array_unique($matches[1]) as $placeholder) {
                if (!in_array($placeholder, self::ALLOWED_PLACEHOLDERS, true)) {
                    return $placeholder;
                }
            }
        }

        return null;
    }

    /**
     * Substitutes every placeholder in $template with a harmless sample value and checks that the
     * result is a well-formed https URL. This only validates the template's static skeleton (the
     * parts outside of placeholders) - it cannot know a real part's values.
     */
    public static function resolvesToAllowedUrlWithSampleValues(string $template): bool
    {
        $sample = preg_replace_callback(
            '/\{([a-zA-Z]+)}/',
            static fn (array $m): string => rawurlencode('sample-' . $m[1]),
            $template
        );

        return str_starts_with($sample, 'https://') && filter_var($sample, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Convenience combined check used by callers that only need a yes/no answer (e.g. the API's
     * validation callback). Returns null if $template is fine, or one of 'unknown_placeholder' /
     * 'invalid_template' identifying which check failed.
     */
    public static function validate(string $template): ?string
    {
        if (self::findUnknownPlaceholder($template) !== null) {
            return 'unknown_placeholder';
        }

        if (!self::resolvesToAllowedUrlWithSampleValues($template)) {
            return 'invalid_template';
        }

        return null;
    }
}
