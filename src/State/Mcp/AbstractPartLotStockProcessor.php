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

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Shared by the withdraw/add/stocktake part-lot stock-adjustment MCP processors: resolving the lot by id, the
 * manual permission check, parsing+validating the optional timestamp, and resolving the lot's part. Each
 * subclass still calls PartLotWithdrawAddHelper itself, since the actual helper method and its argument list
 * differs per operation - only the surrounding wiring is shared here.
 */
abstract class AbstractPartLotStockProcessor
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    protected function resolveLot(int $id): PartLot
    {
        $lot = $this->entityManager->find(PartLot::class, $id);
        if (!$lot instanceof PartLot) {
            throw new NotFoundHttpException(sprintf('Part lot with id %d not found.', $id));
        }

        return $lot;
    }

    /**
     * Manual check - the McpTool's `security` attribute is not enforced by the MCP call pipeline (see Part.php).
     */
    protected function authorize(string $attribute, PartLot $lot): void
    {
        if (!$this->authorizationChecker->isGranted($attribute, $lot)) {
            throw new AccessDeniedException(sprintf('Access denied to perform "%s" on part lot with id %d.', $attribute, $lot->getID()));
        }
    }

    /**
     * Parses the optional ISO 8601 timestamp argument every stock tool accepts, rejecting one more than 20
     * minutes in the future (mirrors PartController::withdrawAddHandler()/stocktakeHandler()).
     */
    protected function parseTimestamp(?string $timestamp): ?\DateTime
    {
        if ($timestamp === null) {
            return null;
        }

        $parsed = new \DateTime($timestamp);
        if ($parsed > new \DateTime('+20min')) {
            throw new \LogicException('The timestamp must not be in the future.');
        }

        return $parsed;
    }

    protected function resolvePart(PartLot $lot): Part
    {
        $part = $lot->getPart();
        if (!$part instanceof Part) {
            throw new \LogicException('Part lot has no associated part.');
        }

        return $part;
    }
}
