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

namespace App\Controller\OAuth\NonLocalized;

use App\Entity\UserSystem\ApiTokenLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * RFC 8414 (OAuth 2.0 Authorization Server Metadata) and RFC 9728 (OAuth 2.0 Protected Resource Metadata)
 * discovery endpoints, so OAuth/MCP clients can locate /oauth/authorize, /oauth/token and the RFC 7591 registration
 * endpoint (App\Controller\OAuth\ClientRegistrationController) without any hardcoded configuration.
 *
 * Lives at the fixed /.well-known/... paths both RFCs mandate - unprefixed by locale (see
 * config/routes/oauth_server_controllers.yaml) and, in practice, served by the "dev" firewall's
 * ^/\.well-known/ pattern (config/packages/security.yaml), which is fully public (security: false).
 *
 * The issuer/resource identifier is derived from the current request's scheme+host rather than a fixed
 * config value, so this keeps working unchanged behind any hostname/reverse proxy the instance is reached
 * through.
 *
 * Only reachable at all if the OAuth2 server itself is enabled (OAUTH_SERVER_ENABLED, disabled by
 * default) - see the class-level route condition below.
 */
#[Route('/.well-known', condition: "env('bool:OAUTH_SERVER_ENABLED') == true")]
class DiscoveryController extends AbstractController
{
    public function __construct(
        #[Autowire('%partdb.oauth_server.dcr_enabled%')]
        private readonly bool $oauth_dcr_enabled,
    ) {
    }

    #[Route('/oauth-authorization-server', name: 'oauth2_discovery_authorization_server', methods: ['GET'])]
    public function authorizationServerMetadata(Request $request): JsonResponse
    {
        $issuer = $request->getSchemeAndHttpHost();

        $metadata = [
            'issuer' => $issuer,
            'authorization_endpoint' => $this->generateUrl('oauth2_authorize', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'token_endpoint' => $this->generateUrl('oauth2_token', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'scopes_supported' => self::availableScopes(),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'code_challenge_methods_supported' => ['S256'],
        ];

        // Dynamic Client Registration is a separate opt-in on top of the OAuth2 server (OAUTH_DCR_ENABLED)
        // - clients can still be registered manually by an admin via /tools/oauth_clients regardless.
        if ($this->oauth_dcr_enabled) {
            $metadata['registration_endpoint'] = $this->generateUrl('oauth2_client_register', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return new JsonResponse($metadata);
    }

    #[Route('/oauth-protected-resource', name: 'oauth2_discovery_protected_resource', methods: ['GET'])]
    public function protectedResourceMetadata(Request $request): JsonResponse
    {
        $issuer = $request->getSchemeAndHttpHost();

        return new JsonResponse([
            'resource' => $issuer,
            'authorization_servers' => [$issuer],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => self::availableScopes(),
        ]);
    }

    /**
     * @return list<string>
     */
    private static function availableScopes(): array
    {
        return ApiTokenLevel::advertisedScopes();
    }
}
