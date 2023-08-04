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


namespace App\Services\System;

use Shivas\VersioningBundle\Service\VersionManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Version\Version;

/**
 * This class checks if a new version of Part-DB is available.
 */
class UpdateAvailableManager
{

    private const API_URL = 'https://api.github.com/repos/Part-DB/Part-DB-server/releases/latest';
    private const CACHE_KEY = 'uam_latest_version';
    private const CACHE_TTL = 60 * 60 * 24 * 2; // 2 day

    public function __construct(private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $updateCache, private readonly VersionManagerInterface $versionManager,
        private readonly bool $check_for_updates)
    {

    }

    /**
     * Gets the latest version of Part-DB as string (e.g. "1.2.3").
     * This value is cached for 2 days.
     * @return string
     */
    public function getLatestVersionString(): string
    {
        return $this->getLatestVersionInfo()['version'];
    }

    /**
     * Gets the latest version of Part-DB as Version object.
     */
    public function getLatestVersion(): Version
    {
        return Version::fromString($this->getLatestVersionString());
    }

    /**
     * Gets the URL to the latest version of Part-DB on GitHub.
     * @return string
     */
    public function getLatestVersionUrl(): string
    {
        return $this->getLatestVersionInfo()['url'];
    }

    /**
     * Checks if a new version of Part-DB is available. This value is cached for 2 days.
     * @return bool
     */
    public function isUpdateAvailable(): bool
    {
        //If we don't want to check for updates, we can return false
        if (!$this->check_for_updates) {
            return false;
        }

        $latestVersion = $this->getLatestVersion();
        $currentVersion = $this->versionManager->getVersion();

        return $latestVersion->isGreaterThan($currentVersion);
    }

    /**
     * Get the latest version info. The value is cached for 2 days.
     * @return array
     * @phpstan-return array{version: string}
     */
    private function getLatestVersionInfo(): array
    {
        //If we don't want to check for updates, we can return dummy data
        if (!$this->check_for_updates) {
            return [
                'version' => '0.0.1',
                'url' => 'update-checking-disabled'
            ];
        }

        return $this->updateCache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);
            $response = $this->httpClient->request('GET', self::API_URL);
            $result = $response->toArray();
            $tag_name = $result['tag_name'];

            // Remove the leading 'v' from the tag name
            $version = substr($tag_name, 1);

            return [
                'version' => $version,
                'url' => $result['html_url'],
            ];
        });
    }
}