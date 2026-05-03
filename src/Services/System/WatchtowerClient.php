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

namespace App\Services\System;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for communicating with the Watchtower container updater API.
 * Used to trigger Docker container updates from the Part-DB UI.
 *
 * @see https://containrrr.dev/watchtower/
 */
readonly class WatchtowerClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire(env: 'WATCHTOWER_API_URL')] private string $apiUrl,
        #[Autowire(env: 'WATCHTOWER_API_TOKEN')] private string $apiToken,
    ) {
    }

    /**
     * Whether Watchtower integration is configured (URL and token are set).
     */
    public function isConfigured(): bool
    {
        return $this->apiUrl !== '' && $this->apiToken !== '';
    }

    /**
     * Check if the Watchtower API is reachable.
     * Makes a lightweight HTTP request with a short timeout.
     */
    public function isAvailable(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->httpClient->request('GET', $this->getUpdateEndpoint(), [
                'headers' => $this->getAuthHeaders(),
                'timeout' => 3,
            ]);

            // Any response means Watchtower is reachable
            $statusCode = $response->getStatusCode();
            return $statusCode < 500;
        } catch (\Throwable $e) {
            $this->logger->debug('Watchtower availability check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger a container update via the Watchtower HTTP API.
     * This is fire-and-forget: Watchtower will pull the new image and restart the container.
     *
     * @return bool True if Watchtower accepted the update request
     */
    public function triggerUpdate(): bool
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Watchtower is not configured. Set WATCHTOWER_API_URL and WATCHTOWER_API_TOKEN.');
        }

        try {
            $response = $this->httpClient->request('POST', $this->getUpdateEndpoint(), [
                'headers' => $this->getAuthHeaders(),
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Watchtower update triggered successfully.');
                return true;
            }

            $this->logger->error('Watchtower update request returned HTTP ' . $statusCode);
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to trigger Watchtower update: ' . $e->getMessage());
            return false;
        }
    }

    private function getUpdateEndpoint(): string
    {
        return rtrim($this->apiUrl, '/') . '/v1/update';
    }

    /**
     * @return array<string, string>
     */
    private function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiToken,
        ];
    }
}
