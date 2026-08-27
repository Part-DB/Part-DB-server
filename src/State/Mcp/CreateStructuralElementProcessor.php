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
use App\Entity\Base\AbstractStructuralDBElement;
use App\Mcp\DTO\CreateStructuralElementInput;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Creates a new structural element (Category, Footprint, Manufacturer, StorageLocation, Supplier, ...) - the
 * concrete class is resolved from the operation, exactly like the generic read processors. Field application,
 * the editing-enabled guard and process()/error-wrapping are all inherited - see
 * AbstractStructuralElementMutationProcessor.
 */
final class CreateStructuralElementProcessor extends AbstractStructuralElementMutationProcessor
{
    protected function mutateElement(mixed $data, Operation $operation): AbstractStructuralDBElement
    {
        if (!$data instanceof CreateStructuralElementInput) {
            throw new \InvalidArgumentException('Expected CreateStructuralElementInput');
        }

        $class = $operation->getClass();
        $this->assertStructuralElementClass($class);

        //Manual check - the McpTool's `security` attribute is not enforced by the MCP call pipeline (see Part.php)
        if (!$this->authorizationChecker->isGranted('create', $class)) {
            throw new AccessDeniedException(sprintf('You are not allowed to create a %s.', (new \ReflectionClass($class))->getShortName()));
        }

        /** @var AbstractStructuralDBElement $element */
        $element = new $class();
        $element->setName($data->name);
        $this->applyProvidedFields($element, $data);

        //Manual entity validation - the McpTool's `validate` flag only validates the input DTO, not the entity
        $this->validator->validate($element);

        $this->applyLogComment($data->logComment);

        $this->entityManager->persist($element);
        $this->entityManager->flush();

        return $element;
    }
}
