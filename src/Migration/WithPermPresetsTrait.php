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


namespace App\Migration;

use App\Entity\UserSystem\PermissionData;
use App\Security\Interfaces\HasPermissionsInterface;
use App\Services\UserSystem\PermissionPresetsHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait WithPermPresetsTrait
{
    private ?ContainerInterface $container = null;
    private ?PermissionPresetsHelper $permission_presets_helper = null;

    private function getJSONPermDataFromPreset(string $preset): string
    {
        if ($this->permission_presets_helper === null) {
            throw new \RuntimeException('PermissionPresetsHelper not set! There seems to be some issue with the dependency injection!');
        }

        //Create a virtual user on which we can apply the preset
        $user = new class implements HasPermissionsInterface {

            public PermissionData $perm_data;

            public function __construct()
            {
                $this->perm_data = new PermissionData();
            }

            public function getPermissions(): PermissionData
            {
                return $this->perm_data;
            }
        };

        //Apply the preset to the virtual user
        $this->permission_presets_helper->applyPreset($user, $preset);

        //And return the json data
        return json_encode($user->getPermissions());
    }

    public function setContainer(ContainerInterface $container = null): void
    {
        if ($container) {
            $this->container = $container;
            $this->permission_presets_helper = $container->get(PermissionPresetsHelper::class);
        }
    }
}