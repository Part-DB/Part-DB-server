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
use App\Entity\Attachments\Attachment;
use App\Mcp\DTO\ElementByIdInput;
use App\Services\Attachments\AttachmentManager;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\EmbeddedResource;
use Mcp\Schema\Content\ImageContent;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Returns the actual file content of an internally stored attachment as an MCP tool result, instead of just
 * its metadata (which is already available via get_part_details and the other get_X_details tools). Pictures
 * are returned as an image content block, everything else as an embedded resource (text or base64 blob,
 * depending on the file's mime type) — both understood natively by MCP clients, so we return CallToolResult
 * directly rather than going through the normal output/normalizationContext serialization path.
 */
class GetAttachmentContentProcessor implements ProcessorInterface
{
    /** Files larger than this are rejected, to avoid flooding the AI's context with a huge base64 blob. */
    protected const MAX_FILE_SIZE = 10 * 1024 * 1024;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuthorizationCheckerInterface $authorizationChecker,
        private AttachmentManager $attachmentManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CallToolResult
    {
        if (!$data instanceof ElementByIdInput) {
            throw new BadRequestHttpException('Expected ElementByIdInput');
        }

        $attachment = $this->entityManager->find(Attachment::class, $data->id);

        if (!$attachment instanceof Attachment) {
            throw new NotFoundHttpException(sprintf('Attachment with id %d not found', $data->id));
        }

        if (!$this->authorizationChecker->isGranted('read', $attachment)) {
            throw new AccessDeniedException(sprintf('Access denied to attachment with id %d', $data->id));
        }

        if ($attachment->isSecure() && !$this->authorizationChecker->isGranted('show_private', $attachment)) {
            throw new AccessDeniedException(sprintf('Access denied to the private attachment with id %d', $data->id));
        }

        if (!$attachment->hasInternal()) {
            throw new BadRequestHttpException(sprintf(
                'Attachment with id %d has no internally stored file, it only references an external URL (%s). Fetch that URL directly instead.',
                $data->id,
                $attachment->getExternalPath() ?? '?'
            ));
        }

        if (!$this->attachmentManager->isInternalFileExisting($attachment)) {
            throw new NotFoundHttpException(sprintf('The file associated with attachment id %d is missing on disk', $data->id));
        }

        $size = $this->attachmentManager->getFileSize($attachment);
        if (null !== $size && $size > static::MAX_FILE_SIZE) {
            throw new BadRequestHttpException(sprintf(
                'The file of attachment id %d is %d bytes, which exceeds the %d byte limit for MCP retrieval. Download it directly via the web interface instead.',
                $data->id,
                $size,
                static::MAX_FILE_SIZE
            ));
        }

        $path = $this->attachmentManager->toAbsoluteInternalFilePath($attachment);
        if (null === $path) {
            throw new NotFoundHttpException(sprintf('The file associated with attachment id %d is missing on disk', $data->id));
        }

        $uri = sprintf('attachment://%d/%s', $attachment->getID(), $attachment->getFilename());

        $fileContent = $attachment->isPicture()
            ? ImageContent::fromFile($path)
            : EmbeddedResource::fromFile($uri, $path);

        return CallToolResult::success([
            new TextContent(sprintf('Content of attachment "%s" (id %d)', $attachment->getFilename(), $attachment->getID())),
            $fileContent,
        ]);
    }
}
