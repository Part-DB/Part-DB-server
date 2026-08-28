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
use App\Mcp\DTO\CreatePartInput;
use App\Services\LogSystem\EventCommentHelper;
use App\Settings\AISettings\McpSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Creates a new part from a CreatePartInput. Every field is applied manually (not via the Serializer) because
 * the MCP call pipeline's default provider only offers a much weaker ObjectMapper-based mapping - see
 * AbstractPartMutationProcessor's docblock and the create_part McpTool entry on Part.php for the full rationale.
 *
 * Field application itself is shared with UpdatePartProcessor via AbstractPartMutationProcessor::applyProvidedFields() -
 * a brand-new Part being built here is, field-application-wise, just an update against a still-empty entity. The
 * editing-enabled guard and process()/error-wrapping are also inherited - see AbstractPartMutationProcessor.
 */
final class CreatePartProcessor extends AbstractPartMutationProcessor
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
        if (!$data instanceof CreatePartInput) {
            throw new \InvalidArgumentException('Expected CreatePartInput');
        }

        //Manual check - the McpTool's `security` attribute is not enforced by the MCP call pipeline (see Part.php)
        if (!$this->authorizationChecker->isGranted('create', Part::class)) {
            throw new AccessDeniedException('You are not allowed to create parts.');
        }

        $part = new Part();
        $part->setName($data->name);
        $this->applyProvidedFields($part, $data);

        //Manual entity validation - the McpTool's `validate` flag only validates the input DTO, not the entity
        $this->validator->validate($part);

        $this->applyLogComment($data->logComment);

        $this->entityManager->persist($part);
        $this->entityManager->flush();

        return $part;
    }
}
