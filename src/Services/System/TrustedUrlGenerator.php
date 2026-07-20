<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\System;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Generates absolute URLs for routes whose host can be trusted, for use in contexts where the host must not
 * be attacker-controllable (e.g. links sent out via email).
 *
 * If no TRUSTED_HOSTS is configured, Symfony does not validate the Host header of the incoming request in
 * any way, so it must not be trusted to generate such links (otherwise an attacker could poison them via a
 * forged Host header). In that case, the host/scheme/base path are forced to the ones configured via
 * DEFAULT_URI instead of the ones from the current HTTP request.
 * If TRUSTED_HOSTS is configured, Symfony already rejects requests with a non-matching Host header, so the
 * request's host can be trusted and is used as usual (which allows the app to be reachable under multiple
 * trusted hostnames).
 */
final class TrustedUrlGenerator
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TrustedHostsChecker $trustedHostsChecker,
        #[Autowire('%partdb.default_uri%')]
        private readonly string $defaultUri,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(string $route, array $parameters = []): string
    {
        //If TRUSTED_HOSTS is configured, Symfony already validated the request's Host header, so it can be trusted
        if (!$this->trustedHostsChecker->isTrustedHostsUnconfigured()) {
            return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $parts = parse_url(rtrim($this->defaultUri, '/'));

        $context = $this->urlGenerator->getContext();

        //Backup the original context, so we can restore it after generating the URL
        $original = [
            'host' => $context->getHost(),
            'scheme' => $context->getScheme(),
            'httpPort' => $context->getHttpPort(),
            'httpsPort' => $context->getHttpsPort(),
            'baseUrl' => $context->getBaseUrl(),
        ];

        $scheme = $parts['scheme'] ?? 'https';
        $context->setScheme($scheme);
        $context->setHost($parts['host'] ?? '');
        if (isset($parts['port'])) {
            if ($scheme === 'https') {
                $context->setHttpsPort($parts['port']);
            } else {
                $context->setHttpPort($parts['port']);
            }
        }
        $context->setBaseUrl($parts['path'] ?? '');

        try {
            return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        } finally {
            //Restore the original context, so the rest of the request is not affected
            $context->setHost($original['host']);
            $context->setScheme($original['scheme']);
            $context->setHttpPort($original['httpPort']);
            $context->setHttpsPort($original['httpsPort']);
            $context->setBaseUrl($original['baseUrl']);
        }
    }
}
