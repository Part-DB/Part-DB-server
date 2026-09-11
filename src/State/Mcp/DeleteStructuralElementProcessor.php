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
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Base\PartsContainingRepositoryInterface;
use App\Mcp\DTO\DeleteStructuralElementInput;
use App\Services\LogSystem\EventCommentHelper;
use App\Settings\AISettings\McpSettings;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Permanently deletes a structural element (Category, Footprint, Manufacturer, StorageLocation, Supplier, ...) -
 * the concrete class is resolved from the operation, exactly like the generic read processors, so this single
 * processor serves every type. Mirrors BaseAdminController's web-UI delete flow: refuses deletion while the
 * element still directly contains parts, and moves child elements up to the deleted element's own parent rather
 * than deleting or orphaning them (the web UI's "delete_recursive" option, which deletes the whole subtree, is
 * intentionally not exposed here - a much more destructive operation than this generic tier should offer).
 */
final class DeleteStructuralElementProcessor implements ProcessorInterface
{
    use McpToolErrorHandling;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly EventCommentHelper $eventCommentHelper,
        private readonly McpSettings $mcpSettings,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CallToolResult
    {
        return $this->runCatchingExpectedErrors(function () use ($data, $operation) {
            $this->mcpSettings->assertEditingEnabled();

            return $this->deleteElement($data, $operation);
        });
    }

    private function deleteElement(mixed $data, Operation $operation): CallToolResult
    {
        if (!$data instanceof DeleteStructuralElementInput) {
            throw new \InvalidArgumentException('Expected DeleteStructuralElementInput');
        }

        $class = $operation->getClass();
        if (!is_a($class, AbstractStructuralDBElement::class, true)) {
            throw new \LogicException(sprintf('%s can only be used for resources extending %s.', self::class, AbstractStructuralDBElement::class));
        }

        $element = $this->entityManager->find($class, $data->id);
        if (!$element instanceof AbstractStructuralDBElement) {
            throw new NotFoundHttpException(sprintf('%s with id %d not found.', (new \ReflectionClass($class))->getShortName(), $data->id));
        }

        //Manual check - the McpTool's `security` attribute is not enforced by the MCP call pipeline (see Part.php)
        if (!$this->authorizationChecker->isGranted('delete', $element)) {
            throw new AccessDeniedException(sprintf('Access denied to delete %s with id %d.', (new \ReflectionClass($class))->getShortName(), $data->id));
        }

        if ($element instanceof AbstractPartsContainingDBElement) {
            $repository = $this->entityManager->getRepository($class);
            if ($repository instanceof PartsContainingRepositoryInterface) {
                $count = $repository->getPartsCount($element);
                if ($count > 0) {
                    throw new BadRequestHttpException(sprintf(
                        '"%s" still contains %d part(s) and cannot be deleted. Reassign or delete those parts first.',
                        $element->getName(),
                        $count
                    ));
                }
            }
        }

        $shortName = (new \ReflectionClass($class))->getShortName();
        $name = $element->getName();
        $id = $element->getID();

        //Child elements are moved up to the deleted element's own parent, not deleted themselves (matches the
        //web UI's default, non-recursive delete behavior)
        $parent = $element->getParent();
        $reparented = 0;
        foreach ($element->getSubelements() as $subelement) {
            $subelement->setParent($parent);
            $this->entityManager->persist($subelement);
            ++$reparented;
        }

        if ($data->logComment !== null && $data->logComment !== '' && !$this->eventCommentHelper->isMessageSet()) {
            $this->eventCommentHelper->setMessage($data->logComment);
        }

        $this->entityManager->remove($element);
        $this->entityManager->flush();

        $message = sprintf('%s "%s" (id %d) has been deleted.', $shortName, $name, $id);
        if ($reparented > 0) {
            $message .= sprintf(' %d child element(s) were moved to its former parent.', $reparented);
        }

        return CallToolResult::success([new TextContent($message)]);
    }
}
