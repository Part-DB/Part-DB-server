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
use App\Entity\Parts\Part;
use App\Mcp\DTO\StocktakePartLotInput;
use App\Services\Parts\PartLotWithdrawAddHelper;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Performs a stocktake on a part lot, setting its amount to the given actual value, mirroring
 * PartController::stocktakeHandler(). All logging is delegated to PartLotWithdrawAddHelper; lot resolution,
 * authorization, and timestamp handling are shared with the other stock tools via AbstractPartLotStockProcessor.
 */
final class StocktakePartLotProcessor extends AbstractPartLotStockProcessor implements ProcessorInterface
{
    use McpToolErrorHandling;

    public function __construct(
        EntityManagerInterface $entityManager,
        AuthorizationCheckerInterface $authorizationChecker,
        private readonly PartLotWithdrawAddHelper $withdrawAddHelper,
    ) {
        parent::__construct($entityManager, $authorizationChecker);
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Part|CallToolResult
    {
        return $this->runCatchingExpectedErrors(fn () => $this->stocktakeLot($data));
    }

    private function stocktakeLot(mixed $data): Part
    {
        if (!$data instanceof StocktakePartLotInput) {
            throw new \InvalidArgumentException('Expected StocktakePartLotInput');
        }

        $lot = $this->resolveLot($data->lotId);
        $this->authorize('stocktake', $lot);
        $timestamp = $this->parseTimestamp($data->timestamp);
        $part = $this->resolvePart($lot);

        //PartStockChangedLogEntry::stocktake() requires a non-nullable string comment, unlike this helper's own signature
        $this->withdrawAddHelper->stocktake($lot, $data->actualAmount, $data->comment ?? '', $timestamp);
        $this->entityManager->flush();

        return $part;
    }
}
