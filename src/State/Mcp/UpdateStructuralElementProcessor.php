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
use App\Mcp\DTO\UpdateStructuralElementInput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Partially updates an existing structural element (Category, Footprint, Manufacturer, StorageLocation,
 * Supplier, ...) - the concrete class is resolved from the operation, exactly like the generic read processors.
 * Only fields that were actually present in the raw MCP arguments (UpdateStructuralElementInput::wasProvided())
 * are applied - an omitted field is left completely untouched. Field application, the editing-enabled guard and
 * process()/error-wrapping are all inherited - see AbstractStructuralElementMutationProcessor.
 */
final class UpdateStructuralElementProcessor extends AbstractStructuralElementMutationProcessor
{
    protected function mutateElement(mixed $data, Operation $operation): AbstractStructuralDBElement
    {
        if (!$data instanceof UpdateStructuralElementInput) {
            throw new \InvalidArgumentException('Expected UpdateStructuralElementInput');
        }

        $class = $operation->getClass();
        $this->assertStructuralElementClass($class);

        $element = $this->entityManager->find($class, $data->id);
        if (!$element instanceof AbstractStructuralDBElement) {
            throw new NotFoundHttpException(sprintf('%s with id %d not found.', (new \ReflectionClass($class))->getShortName(), $data->id));
        }

        //Manual check - the McpTool's `security` attribute is not enforced by the MCP call pipeline (see Part.php)
        if (!$this->authorizationChecker->isGranted('edit', $element)) {
            throw new AccessDeniedException(sprintf('Access denied to edit %s with id %d.', (new \ReflectionClass($class))->getShortName(), $data->id));
        }

        if ($data->wasProvided('name')) {
            $element->setName($data->name ?? '');
        }
        $this->applyProvidedFields($element, $data);

        //Manual entity validation - the McpTool's `validate` flag only validates the input DTO, not the entity
        $this->validator->validate($element);

        $this->applyLogComment($data->logComment);

        $this->entityManager->flush();

        return $element;
    }
}
