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
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\UserSystem\User;
use App\Services\LabelSystem\AgentJobCreator;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Lists the label profiles and the current user's label printers as JSON, so an AI agent can choose them for
 * the print_labels tool.
 */
final class GetLabelPrintingOptionsProcessor implements ProcessorInterface
{
    use McpToolErrorHandling;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly AgentJobCreator $agentJobCreator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CallToolResult
    {
        return $this->runCatchingExpectedErrors(fn () => $this->options());
    }

    private function options(): CallToolResult
    {
        //Manual check - the McpTool's `security` attribute is not enforced by the MCP call pipeline (see Part.php)
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->security->isGranted('@labels.read_profiles')) {
            throw new AccessDeniedException('Access denied: listing label profiles needs the "Read profiles" permission.');
        }

        $profiles = [];
        foreach ($this->entityManager->getRepository(LabelProfile::class)->findBy([], ['name' => 'ASC']) as $profile) {
            if (!$this->security->isGranted('read', $profile)) {
                continue;
            }
            $options = $profile->getOptions();
            $profiles[] = [
                'id' => $profile->getID(),
                'name' => $profile->getName(),
                'targetType' => $options->getSupportedElement()->value,
                'width' => $options->getWidth(),
                'height' => $options->getHeight(),
                'defaultPrinter' => $profile->getDefaultPrinter() !== '' ? $profile->getDefaultPrinter() : null,
            ];
        }

        $printers = [];
        if ($this->security->isGranted('@labels.create_labels')) {
            foreach ($this->agentJobCreator->printersFor($user) as $printer) {
                $printers[] = [
                    'printer' => $printer['agent']->getID().'|'.$printer['id'],
                    'name' => $printer['name'],
                    'agent' => $printer['agent']->getName(),
                    'online' => $printer['agent']->isOnline(),
                ];
            }
        }

        return CallToolResult::success([new TextContent(json_encode(['profiles' => $profiles, 'printers' => $printers],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))]);
    }
}
