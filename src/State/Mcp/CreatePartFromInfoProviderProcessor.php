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
use App\ApiResource\InfoProviderSystem\InfoProviderPartWriteResult;
use App\Entity\Parts\Part;
use App\Exceptions\InfoProviderNotActiveException;
use App\Mcp\DTO\InfoProviderCreatePartInput;
use App\Services\InfoProviderSystem\PartFromProviderWriter;
use App\Services\LogSystem\EventCommentHelper;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Handles POST /api/info_providers/create_part and the create_part_from_info_provider MCP tool.
 */
final readonly class CreatePartFromInfoProviderProcessor implements ProcessorInterface
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private PartFromProviderWriter $writer,
        private EventCommentHelper $eventCommentHelper,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InfoProviderPartWriteResult
    {
        if (!$data instanceof InfoProviderCreatePartInput) {
            throw new BadRequestHttpException('Expected InfoProviderCreatePartInput');
        }

        //Manual check - the McpTool's `security` attribute is not enforced by the MCP call pipeline (see Part.php)
        if (!$this->authorizationChecker->isGranted('create', Part::class)) {
            throw new AccessDeniedException('You are not allowed to create parts.');
        }
        if (!$this->authorizationChecker->isGranted('@info_providers.create_parts')) {
            throw new AccessDeniedException('You are not allowed to use the info providers.');
        }

        $this->eventCommentHelper->setMessage('Created from info provider via API');

        try {
            return $this->writer->create($data->provider_key, $data->provider_id, $data->category_id, $data->no_cache,
                $data->dry_run);
        } catch (InfoProviderNotActiveException|\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
    }
}
