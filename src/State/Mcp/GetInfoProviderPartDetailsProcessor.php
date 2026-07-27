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

namespace App\State\Mcp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Exceptions\InfoProviderNotActiveException;
use App\Mcp\DTO\InfoProviderPartDetailsInput;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GetInfoProviderPartDetailsProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly PartInfoRetriever $infoRetriever,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$data instanceof InfoProviderPartDetailsInput) {
            throw new BadRequestHttpException('Expected InfoProviderPartDetailsInput');
        }

        try {
            return $this->infoRetriever->getDetails($data->provider_key, $data->provider_id);
        } catch (InfoProviderNotActiveException|\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
    }
}
