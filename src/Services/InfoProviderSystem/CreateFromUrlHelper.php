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


namespace App\Services\InfoProviderSystem;

use App\Entity\UserSystem\User;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class CreateFromUrlHelper
{
    public function __construct(private Security $security, private ProviderRegistry $providerRegistry)
    {
    }

    /**
     * Checks if at least one provider can create parts from an URL and the current user is allowed to use it.
     * This is used to determine if the "From URL" feature should be shown to the user.
     * @return bool
     */
    public function canCreateFromUrl(): bool
    {
        if (!$this->security->isGranted('@info_providers.create_parts')) {
            return false;
        }

        //Check if either the generic web provider or the ai web provider is active
        $genericWebProvider = $this->providerRegistry->getProviderByKey('generic_web');
        $aiWebProvider = $this->providerRegistry->getProviderByKey('ai_web');

        return $genericWebProvider->isActive() || $aiWebProvider->isActive();
    }
}
