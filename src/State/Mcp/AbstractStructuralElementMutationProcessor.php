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
use ApiPlatform\Validator\ValidatorInterface;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Mcp\DTO\AbstractStructuralElementWriteInput;
use App\Services\LogSystem\EventCommentHelper;
use App\Settings\AISettings\McpSettings;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Shared by CreateStructuralElementProcessor and UpdateStructuralElementProcessor, generic across every
 * AbstractStructuralDBElement subclass (Category, Footprint, Manufacturer, StorageLocation, Supplier, ...) - the
 * concrete class is resolved from the operation, exactly like GetStructuralElementDetailsProcessor does for reads.
 *
 * Owns the process() method as a template: the global editing-enabled guard and expected-error-to-CallToolResult
 * wrapping (McpToolErrorHandling) happen exactly once, here, so no subclass can forget them - each subclass only
 * implements mutateElement() with its own entity-resolution/permission-check/field-application logic.
 */
abstract class AbstractStructuralElementMutationProcessor implements ProcessorInterface
{
    use McpToolErrorHandling;

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly ValidatorInterface $validator,
        protected readonly EventCommentHelper $eventCommentHelper,
        protected readonly McpSettings $mcpSettings,
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    final public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AbstractStructuralDBElement|CallToolResult
    {
        return $this->runCatchingExpectedErrors(function () use ($data, $operation) {
            $this->mcpSettings->assertEditingEnabled();

            return $this->mutateElement($data, $operation);
        });
    }

    abstract protected function mutateElement(mixed $data, Operation $operation): AbstractStructuralDBElement;

    /**
     * @param class-string $class
     */
    protected function assertStructuralElementClass(string $class): void
    {
        if (!is_a($class, AbstractStructuralDBElement::class, true)) {
            throw new \LogicException(sprintf('%s can only be used for resources extending %s.', static::class, AbstractStructuralDBElement::class));
        }
    }

    /**
     * Resolves a "parentId" to an existing element of the *same* concrete class as $class (a Category's parent
     * is always another Category, etc.).
     *
     * @param class-string<AbstractStructuralDBElement> $class
     */
    protected function resolveParent(string $class, int $id): AbstractStructuralDBElement
    {
        $parent = $this->entityManager->find($class, $id);
        if (!$parent instanceof AbstractStructuralDBElement) {
            throw new NotFoundHttpException(sprintf('%s with id %d not found.', (new \ReflectionClass($class))->getShortName(), $id));
        }

        return $parent;
    }

    /**
     * Applies every field AbstractStructuralElementWriteInput declares onto $element, guarded uniformly by
     * wasProvided() - used identically by both create (against a brand-new element) and update.
     */
    protected function applyProvidedFields(AbstractStructuralDBElement $element, AbstractStructuralElementWriteInput $data): void
    {
        if ($data->wasProvided('comment')) {
            $element->setComment($data->comment ?? '');
        }
        if ($data->wasProvided('notSelectable')) {
            $element->setNotSelectable($data->notSelectable ?? false);
        }
        if ($data->wasProvided('parentId')) {
            $element->setParent($data->parentId !== null ? $this->resolveParent($element::class, $data->parentId) : null);
        }
        if ($data->wasProvided('alternativeNames')) {
            $element->setAlternativeNames($data->alternativeNames);
        }
    }

    /**
     * Feeds a non-empty logComment into the audit log, shared by create/update.
     */
    protected function applyLogComment(?string $logComment): void
    {
        if ($logComment !== null && $logComment !== '' && !$this->eventCommentHelper->isMessageSet()) {
            $this->eventCommentHelper->setMessage($logComment);
        }
    }
}
