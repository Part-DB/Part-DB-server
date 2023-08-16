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


namespace App\State;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use ApiPlatform\State\ProviderInterface;
use App\Security\ApiTokenAuthenticatedToken;
use Symfony\Bundle\SecurityBundle\Security;


class CurrentApiTokenProvider implements ProviderInterface
{

    public function __construct(private readonly Security $security)
    {

    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $securityToken = $this->security->getToken();
        if (!$securityToken instanceof ApiTokenAuthenticatedToken) {
            return null;
        }

        return $securityToken->getApiToken();
    }
}