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

use ApiPlatform\Validator\ValidatorInterface;
use App\Entity\Parts\Part;
use App\Mcp\DTO\UpdatePartInput;
use App\Services\LogSystem\EventCommentHelper;
use App\Settings\AISettings\McpSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Partially updates an existing part from an UpdatePartInput. Only fields that were actually present in the raw
 * MCP arguments (UpdatePartInput::wasProvided()) are applied - an omitted field is left completely untouched,
 * including nested collections, which are only reconciled at all if their top-level key was provided.
 *
 * Field application itself is shared with CreatePartProcessor via AbstractPartMutationProcessor::applyProvidedFields().
 * The editing-enabled guard and process()/error-wrapping are also inherited - see AbstractPartMutationProcessor.
 */
final class UpdatePartProcessor extends AbstractPartMutationProcessor
{
    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        EventCommentHelper $eventCommentHelper,
        McpSettings $mcpSettings,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
        parent::__construct($entityManager, $validator, $eventCommentHelper, $mcpSettings);
    }

    protected function mutatePart(mixed $data): Part
    {
        if (!$data instanceof UpdatePartInput) {
            throw new \InvalidArgumentException('Expected UpdatePartInput');
        }

        $part = $this->entityManager->find(Part::class, $data->id);
        if (!$part instanceof Part) {
            throw new NotFoundHttpException(sprintf('Part with id %d not found.', $data->id));
        }

        //Manual check - the McpTool's `security` attribute is not enforced by the MCP call pipeline (see Part.php)
        if (!$this->authorizationChecker->isGranted('edit', $part)) {
            throw new AccessDeniedException(sprintf('Access denied to part with id %d.', $data->id));
        }

        if ($data->wasProvided('name')) {
            $part->setName($data->name ?? '');
        }
        $this->applyProvidedFields($part, $data);

        //Manual entity validation - the McpTool's `validate` flag only validates the input DTO, not the entity
        $this->validator->validate($part);

        $this->applyLogComment($data->logComment);

        $this->entityManager->flush();

        return $part;
    }
}
