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


namespace App\Services\AI;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * This is a wrapper, to allow accepting all models, even if they are not contained in the decorated ModelCatalogInterface.
 * This is a workaround for outdated/incomplete model catalogs provided by AI platforms, which do not contain all available models, or do not update their catalogs frequently enough.
 */
#[AsDecorator('ai.platform.model_catalog.lmstudio')]
#[AsDecorator('ai.platform.model_catalog.openrouter')]
final readonly class AcceptAllModelsCatalog implements ModelCatalogInterface
{

    public function __construct(private ModelCatalogInterface $decorated)
    {
    }

    public function getModel(string $modelName): Model
    {
        //Use the actual values when its available.
        try {
            return $this->decorated->getModel($modelName);
        } catch (ModelNotFoundException $e) {
            //If the model is not found, return a generic model with the given name and no capabilities.
            return new CompletionsModel($modelName, []);
        }
    }

    public function getModels(): array
    {
        //Return the actual models catalog here for correct autocompletition
        return $this->decorated->getModels();
    }
}
