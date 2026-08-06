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


namespace App\Services\InfoProviderSystem\Providers;

use App\Settings\InfoProviderSystem\TMESettings;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TMEClient
{
    public const BASE_URI = 'https://api.tme.eu';

    private const CACHE_KEY_PREFIX = 'tme_oauth_token_';

    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private int $tokenExpiry = 0;
    private bool $loadedFromCache = false;

    public function __construct(
        private readonly HttpClientInterface $tmeClient,
        private readonly TMESettings $settings,
        private readonly CacheItemPoolInterface $infoProviderCache,
    )
    {
    }

    /**
     * The cache key is derived from the configured credentials, so changing the
     * token/secret in the settings automatically invalidates any previously cached token.
     */
    private function getCacheKey(): string
    {
        return self::CACHE_KEY_PREFIX . hash('xxh3', $this->settings->apiToken . ':' . $this->settings->apiSecret);
    }

    /**
     * Loads a previously persisted token (if any) into the in-memory properties, so it can be
     * reused across requests instead of always starting a fresh client_credentials flow.
     */
    private function loadFromCache(): void
    {
        if ($this->loadedFromCache) {
            return;
        }
        $this->loadedFromCache = true;

        $item = $this->infoProviderCache->getItem($this->getCacheKey());
        if ($item->isHit()) {
            $data = $item->get();
            $this->accessToken = $data['access_token'];
            $this->refreshToken = $data['refresh_token'];
            $this->tokenExpiry = $data['expiry'];
        }
    }

    private function saveToCache(): void
    {
        $item = $this->infoProviderCache->getItem($this->getCacheKey());
        $item->set([
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expiry' => $this->tokenExpiry,
        ]);
        // Keep the refresh token available in the cache for a while after the access token itself expired
        $item->expiresAfter(max($this->tokenExpiry - time(), 0) + 60 * 60 * 24 * 7);
        $this->infoProviderCache->save($item);
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken === null) {
            $this->loadFromCache();
        }

        // Return cached token if still valid (30-second safety margin before expiry)
        if ($this->accessToken !== null && time() < $this->tokenExpiry - 30) {
            return $this->accessToken;
        }

        // Try refreshing before falling back to a full client_credentials flow
        if ($this->refreshToken !== null) {
            try {
                $this->fetchToken('refresh_token', ['refresh_token' => $this->refreshToken]);
                return $this->accessToken;
            } catch (\Throwable) {
                $this->refreshToken = null;
            }
        }

        $this->fetchToken('client_credentials');
        return $this->accessToken;
    }

    private function fetchToken(string $grantType, array $extraParams = []): void
    {
        $credentials = base64_encode($this->settings->apiToken . ':' . $this->settings->apiSecret);

        $response = $this->tmeClient->request('POST', self::BASE_URI . '/auth/token', [
            'headers' => [
                'Authorization' => 'Basic ' . $credentials,
            ],
            'body' => array_merge(['grant_type' => $grantType], $extraParams),
        ]);

        $data = $response->toArray();
        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = time() + (int) $data['expires_in'];
        $this->refreshToken = $data['refresh_token'] ?? null;

        $this->saveToCache();
    }

    /**
     * Makes an authenticated GET request to the given v2 endpoint.
     *
     * @param string $endpoint  Path relative to BASE_URI, e.g. 'products/search'
     * @param array  $queryParams  Query parameters; arrays are serialised as PHP-style brackets by Symfony's HTTP client
     */
    public function makeRequest(string $endpoint, array $queryParams = []): ResponseInterface
    {
        $token = $this->getAccessToken();

        return $this->tmeClient->request('GET', self::BASE_URI . '/' . $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept-Language' => $this->settings->language,
            ],
            'query' => $queryParams,
        ]);
    }

    public function isUsable(): bool
    {
        return !($this->settings->apiToken === null || $this->settings->apiSecret === null);
    }

    /**
     * In API v2 all tokens are private (50-char token + 20-char secret); kept for
     * backwards-compatibility with code that checks this flag.
     */
    public function isUsingPrivateToken(): bool
    {
        return strlen($this->settings->apiToken ?? '') >= 50;
    }
}
