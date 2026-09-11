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
use App\Mcp\DTO\AddPartStockInput;
use App\Services\Parts\PartLotWithdrawAddHelper;
use App\Settings\AISettings\McpSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds stock to a part lot, mirroring PartController::withdrawAddHandler()'s 'add' action. All stock math,
 * rounding and logging is delegated to PartLotWithdrawAddHelper; lot resolution, authorization, timestamp
 * handling, the editing-enabled guard and process()/error-wrapping are all inherited - see AbstractPartLotStockProcessor.
 */
final class AddPartStockProcessor extends AbstractPartLotStockProcessor
{
    public function __construct(
        EntityManagerInterface $entityManager,
        AuthorizationCheckerInterface $authorizationChecker,
        McpSettings $mcpSettings,
        private readonly PartLotWithdrawAddHelper $withdrawAddHelper,
    ) {
        parent::__construct($entityManager, $authorizationChecker, $mcpSettings);
    }

    protected function adjustStock(mixed $data): Part
    {
        if (!$data instanceof AddPartStockInput) {
            throw new \InvalidArgumentException('Expected AddPartStockInput');
        }

        $lot = $this->resolveLot($data->lotId);
        $this->authorize('add', $lot);
        $timestamp = $this->parseTimestamp($data->timestamp);
        $part = $this->resolvePart($lot);

        //PartStockChangedLogEntry::add() requires a non-nullable string comment, unlike this helper's own signature
        $this->withdrawAddHelper->add($lot, $data->amount, $data->comment ?? '', $timestamp);
        $this->entityManager->flush();

        return $part;
    }
}
