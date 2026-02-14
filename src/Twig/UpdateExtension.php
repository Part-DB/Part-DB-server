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


namespace App\Twig;

use Twig\Attribute\AsTwigFunction;
use App\Services\System\UpdateAvailableFacade;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for update-related functions.
 */
final class UpdateExtension
{
    public function __construct(private readonly UpdateAvailableFacade $updateAvailableManager,
        private readonly Security $security)
    {

    }

    /**
     * Check if an update is available and the user has permission to see it.
     */
    #[AsTwigFunction(name: 'is_update_available')]
    public function isUpdateAvailable(): bool
    {
        // Only show to users with the show_updates permission
        if (!$this->security->isGranted('@system.show_updates')) {
            return false;
        }

        return $this->updateAvailableManager->isUpdateAvailable();
    }

    /**
     * Get the latest available version string.
     */
    #[AsTwigFunction(name: 'get_latest_version')]
    public function getLatestVersion(): string
    {
        return $this->updateAvailableManager->getLatestVersionString();
    }

    /**
     * Get the URL to the latest version release page.
     */
    #[AsTwigFunction(name: 'get_latest_version_url')]
    public function getLatestVersionUrl(): string
    {
        return $this->updateAvailableManager->getLatestVersionUrl();
    }
}
