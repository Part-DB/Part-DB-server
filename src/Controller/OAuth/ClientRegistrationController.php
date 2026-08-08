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

namespace App\Controller\OAuth;

use App\Entity\UserSystem\ApiTokenLevel;
use App\Entity\UserSystem\DynamicallyRegisteredOAuthClient;
use App\Services\OAuth\OAuthRedirectUriValidator;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ScopeManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 7591 Dynamic Client Registration. Deliberately open/unauthenticated - any client may self-register
 * a public (secret-less) OAuth2 client, since the actual access-control gate for the whole OAuth server
 * is the per-user consent screen at /oauth/authorize (see App\EventListener\OAuth\AuthorizationConsentListener),
 * not client registration. Only Authorization Code + PKCE / refresh_token are ever issued to registered
 * clients (config/packages/league_oauth2_server.yaml), so registering a client grants no access by itself.
 *
 * Rate-limited per client IP (config/packages/rate_limiter.yaml, "oauth_client_registration") since this
 * endpoint has no other gate protecting it from mass-registration abuse.
 *
 * Only reachable if both the OAuth2 server and Dynamic Client Registration specifically are enabled
 * (OAUTH_SERVER_ENABLED and OAUTH_DCR_ENABLED, both disabled by default) - see the route condition below.
 * Clients can still be registered manually by an admin via /tools/oauth_clients regardless of this flag.
 *
 * Self-registered clients (e.g. auto-provisioning MCP apps) may request at most the "edit" scope - see
 * validateScopes(). Requesting "admin" or "full" requires an administrator to register the client by hand.
 *
 * Every client created here also gets an App\Entity\UserSystem\DynamicallyRegisteredOAuthClient marker row,
 * so the admin overview (App\Controller\OAuthClientAdminController) can flag it as self-registered - see
 * that entity's docblock for why this isn't just a field on the bundle's own Client model.
 */
#[Route('/oauth')]
class ClientRegistrationController extends AbstractController
{
    /** @var list<string> */
    private const ALLOWED_GRANT_TYPES = ['authorization_code', 'refresh_token'];

    /** @var list<string> */
    private const ALLOWED_RESPONSE_TYPES = ['code'];

    private const MAX_REDIRECT_URIS = 10;
    private const MAX_REDIRECT_URI_LENGTH = 2000;
    private const MAX_CLIENT_NAME_LENGTH = 128;
    private const MAX_BODY_LENGTH = 65536;

    /**
     * @param list<string> $defaultScopes
     */
    public function __construct(
        private readonly ClientManagerInterface $clientManager,
        private readonly ScopeManagerInterface $scopeManager,
        private readonly OAuthRedirectUriValidator $redirectUriValidator,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'limiter.oauth_client_registration')]
        private readonly RateLimiterFactory $registrationLimiter,
        #[Autowire('%league.oauth2_server.scopes.default%')]
        private readonly array $defaultScopes,
    ) {
    }

    #[Route('/register', name: 'oauth2_client_register', methods: ['POST'], condition: "(env('OAUTH_SERVER_ENABLED') == '1' or env('OAUTH_SERVER_ENABLED') == 'true') and (env('OAUTH_DCR_ENABLED') == '1' or env('OAUTH_DCR_ENABLED') == 'true')")]
    public function register(Request $request): JsonResponse
    {
        $limit = $this->registrationLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            $retryAfterSeconds = max(0, $limit->getRetryAfter()->getTimestamp() - time());

            return new JsonResponse(
                ['error' => 'temporarily_unavailable', 'error_description' => 'Too many client registration requests, please try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) $retryAfterSeconds]
            );
        }

        $content = $request->getContent();
        if (\strlen($content) > self::MAX_BODY_LENGTH) {
            return $this->error('invalid_client_metadata', 'Request body is too large.');
        }

        $data = json_decode($content, true);
        if (!\is_array($data)) {
            return $this->error('invalid_client_metadata', 'Request body must be a JSON object.');
        }

        $redirectUris = $this->validateRedirectUris($data['redirect_uris'] ?? null);
        if ($redirectUris instanceof JsonResponse) {
            return $redirectUris;
        }

        $authMethod = $data['token_endpoint_auth_method'] ?? 'none';
        if ('none' !== $authMethod) {
            return $this->error('invalid_client_metadata', 'Only public clients are supported; token_endpoint_auth_method must be "none".');
        }

        $grantTypes = $data['grant_types'] ?? self::ALLOWED_GRANT_TYPES;
        if (!\is_array($grantTypes) || [] === $grantTypes || [] !== array_diff($grantTypes, self::ALLOWED_GRANT_TYPES)) {
            return $this->error('invalid_client_metadata', 'grant_types may only contain "authorization_code" and "refresh_token".');
        }

        $responseTypes = $data['response_types'] ?? self::ALLOWED_RESPONSE_TYPES;
        if (!\is_array($responseTypes) || [] === $responseTypes || [] !== array_diff($responseTypes, self::ALLOWED_RESPONSE_TYPES)) {
            return $this->error('invalid_client_metadata', 'response_types may only contain "code".');
        }

        $clientName = $data['client_name'] ?? 'Unnamed OAuth client';
        if (!\is_string($clientName) || '' === $clientName) {
            return $this->error('invalid_client_metadata', 'client_name must be a non-empty string.');
        }
        $clientName = mb_substr($clientName, 0, self::MAX_CLIENT_NAME_LENGTH);

        $scopes = $this->validateScopes($data['scope'] ?? null);
        if ($scopes instanceof JsonResponse) {
            return $scopes;
        }

        $identifier = bin2hex(random_bytes(16));

        $client = new Client($clientName, $identifier, null);
        $client->setRedirectUris(...array_map(static fn (string $uri): RedirectUri => new RedirectUri($uri), $redirectUris));
        $client->setGrants(...array_map(static fn (string $grant): Grant => new Grant($grant), $grantTypes));
        $client->setScopes(...array_map(static fn (string $scope): Scope => new Scope($scope), $scopes));
        $client->setActive(true);

        $this->clientManager->save($client);

        $this->entityManager->persist(new DynamicallyRegisteredOAuthClient($client));
        $this->entityManager->flush();

        return new JsonResponse([
            'client_id' => $identifier,
            'client_id_issued_at' => time(),
            'client_name' => $clientName,
            'redirect_uris' => $redirectUris,
            'grant_types' => array_values($grantTypes),
            'response_types' => array_values($responseTypes),
            'token_endpoint_auth_method' => 'none',
            'scope' => implode(' ', $scopes),
        ], Response::HTTP_CREATED);
    }

    /**
     * @return list<string>|JsonResponse
     */
    private function validateRedirectUris(mixed $redirectUrisRaw): array|JsonResponse
    {
        if (!\is_array($redirectUrisRaw) || [] === $redirectUrisRaw || \count($redirectUrisRaw) > self::MAX_REDIRECT_URIS) {
            return $this->error('invalid_redirect_uri', 'redirect_uris must be a non-empty array of at most '.self::MAX_REDIRECT_URIS.' URIs.');
        }

        $redirectUris = [];
        foreach ($redirectUrisRaw as $uri) {
            if (!\is_string($uri) || \strlen($uri) > self::MAX_REDIRECT_URI_LENGTH || !$this->redirectUriValidator->isAllowed($uri)) {
                return $this->error('invalid_redirect_uri', \sprintf('"%s" is not an allowed redirect URI.', \is_string($uri) ? $uri : get_debug_type($uri)));
            }
            $redirectUris[] = $uri;
        }

        return $redirectUris;
    }

    /**
     * @return list<string>|JsonResponse
     */
    private function validateScopes(mixed $scopeString): array|JsonResponse
    {
        if (null === $scopeString) {
            return $this->defaultScopes;
        }

        if (!\is_string($scopeString)) {
            return $this->error('invalid_client_metadata', 'scope must be a space-delimited string.');
        }

        $scopes = array_values(array_filter(explode(' ', $scopeString), static fn (string $s): bool => '' !== $s));
        if ([] === $scopes) {
            return $this->defaultScopes;
        }

        foreach ($scopes as $scope) {
            if (null === $this->scopeManager->find($scope)) {
                return $this->error('invalid_client_metadata', \sprintf('Unknown scope "%s".', $scope));
            }

            $level = $this->levelForScope($scope);
            if ($level instanceof ApiTokenLevel && $level->value > ApiTokenLevel::EDIT->value) {
                // Self-registration is unattended - nobody reviews these requests before the client
                // exists, unlike manual registration via OAuthClientAdminController. The actual access
                // grant still requires a user to approve it on the consent screen (and a user can never
                // grant more than what the client requested here), but letting a script request
                // admin/full up front would make that consent screen's own scope cap meaningless as a
                // safety net. Admin/full clients must be registered by hand by an administrator instead.
                return $this->error('invalid_scope', \sprintf('Dynamically registered clients may request at most the "%s" scope; "%s" requires an administrator to register the client manually.', strtolower(ApiTokenLevel::EDIT->name), $scope));
            }
        }

        return $scopes;
    }

    private function levelForScope(string $scope): ?ApiTokenLevel
    {
        foreach (ApiTokenLevel::cases() as $level) {
            if (strtolower($level->name) === $scope) {
                return $level;
            }
        }

        return null;
    }

    private function error(string $error, string $description): JsonResponse
    {
        return new JsonResponse(['error' => $error, 'error_description' => $description], Response::HTTP_BAD_REQUEST);
    }
}
