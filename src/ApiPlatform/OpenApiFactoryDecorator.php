<?php
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

declare(strict_types=1);


namespace App\ApiPlatform;

use App\Entity\UserSystem\ApiTokenLevel;
use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\OAuthFlow;
use ApiPlatform\OpenApi\Model\OAuthFlows;
use ApiPlatform\OpenApi\Model\SecurityScheme;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsDecorator('api_platform.openapi.factory')]
class OpenApiFactoryDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);
        $securitySchemes = $openApi->getComponents()->getSecuritySchemes() ?: new \ArrayObject();
        $securitySchemes['access_token'] = new SecurityScheme(
            type: 'http',
            description: 'Use an API token to authenticate',
            name: 'Authorization',
            scheme: 'bearer',
        );

        // Advertises the OAuth2 authorization code (+ PKCE) flow (see config/packages/league_oauth2_server.yaml
        // and docs/api/authentication.md) so API clients (and MCP clients doing OpenAPI-based discovery) can
        // find /authorize + /token without hardcoding them, and self-register a client via RFC 7591 DCR
        // (POST /oauth/register, mentioned in the description since OpenAPI's OAuthFlow model has no
        // dedicated registrationEndpoint field) instead of requiring a manually created personal API token.
        $securitySchemes['oauth2'] = new SecurityScheme(
            type: 'oauth2',
            description: 'Authenticate as an OAuth2 client (authorization code + PKCE). Clients can self-register '
                .'via Dynamic Client Registration at POST /oauth/register (RFC 7591), or use the discovery '
                .'documents at /.well-known/oauth-authorization-server and /.well-known/oauth-protected-resource.',
            flows: new OAuthFlows(
                authorizationCode: new OAuthFlow(
                    authorizationUrl: $this->urlGenerator->generate('oauth2_authorize', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    tokenUrl: $this->urlGenerator->generate('oauth2_token', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    // "admin"/"full" are intentionally left out here (see ApiTokenLevel::advertisedScopes())
                    // - self-registered (DCR) clients are capped at "edit" server-side anyway, so
                    // advertising the elevated scopes here would just invite clients to request access
                    // they can't obtain.
                    scopes: new \ArrayObject(array_intersect_key([
                        'read_only' => 'Read (non-sensitive) data',
                        'edit' => 'Read and edit (non-sensitive) data',
                        'admin' => 'Some administrative tasks (e.g. viewing all log entries)',
                        'full' => 'Everything the user can do',
                    ], array_flip(ApiTokenLevel::advertisedScopes()))),
                ),
            ),
        );

        return $openApi;
    }
}