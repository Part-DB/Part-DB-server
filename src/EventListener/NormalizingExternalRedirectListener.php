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


namespace App\EventListener;

use Nelmio\SecurityBundle\EventListener\ExternalRedirectListener;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Decorates nelmio's external redirect listener to close a bypass in its host detection.
 *
 * Nelmio uses parse_url() to find the host of a redirect target and treats targets without a host as local.
 * Browsers however parse URLs with the special schemes (http, https, ws, wss, ftp) leniently, and ignore missing or
 * additional slashes after the scheme: "https:/evil.example" or "https:///evil.example" lead to evil.example, while
 * parse_url() finds no host (or fails completely) and nelmio lets the redirect pass.
 *
 * This decorator normalizes such Location headers to the canonical "scheme://host" form a browser would use, before
 * nelmio inspects them, so that nelmio's check (including allow list, abort/override and logging) works as intended.
 */
#[AsDecorator(decorates: 'nelmio_security.external_redirect_listener', onInvalid: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]
final class NormalizingExternalRedirectListener
{
    /**
     * Matches a special scheme, followed by anything else than exactly two slashes (the only form parse_url() handles).
     * Backslashes and control chars are not handled here, as nelmio already rejects them.
     */
    private const MALFORMED_SPECIAL_SCHEME_REGEX = '#^(https?|wss?|ftp):(?!//[^/])/*#i';

    public function __construct(
        #[AutowireDecorated]
        private readonly ExternalRedirectListener $inner,
    ) {
    }

    public function onKernelResponse(ResponseEvent $e): void
    {
        $response = $e->getResponse();
        if ($e->isMainRequest() && $response->isRedirect()) {
            $target = $response->headers->get('Location');
            if (null !== $target) {
                $normalized = self::normalizeTarget($target);
                if ($normalized !== $target) {
                    $response->headers->set('Location', $normalized);
                }
            }
        }

        $this->inner->onKernelResponse($e);
    }

    /**
     * Normalizes redirect targets with a special scheme but malformed authority part ("https:/host", "https:host",
     * "https:///host") to the form "https://host", as browsers interpret them that way.
     * Other targets are returned unchanged.
     */
    public static function normalizeTarget(string $target): string
    {
        return preg_replace_callback(
            self::MALFORMED_SPECIAL_SCHEME_REGEX,
            static fn (array $m): string => strtolower($m[1]).'://',
            $target,
            1
        ) ?? $target;
    }
}
