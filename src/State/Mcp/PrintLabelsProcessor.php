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
use App\Entity\AgentSystem\Agent;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\PrintLabelsInput;
use App\Services\LabelSystem\AgentJobCreator;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Prints labels through one of the current user's print agents, like the Print button in the label menus: creates an
 * agent job for the chosen (or default) label profile and printer.
 */
final class PrintLabelsProcessor implements ProcessorInterface
{
    use McpToolErrorHandling;

    /** Labels per call, so a confused AI agent can't print a whole roll at once */
    public const MAX_LABELS = 50;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly AgentJobCreator $agentJobCreator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CallToolResult
    {
        return $this->runCatchingExpectedErrors(fn () => $this->print($data));
    }

    private function print(mixed $data): CallToolResult
    {
        if (!$data instanceof PrintLabelsInput) {
            throw new \InvalidArgumentException('Expected PrintLabelsInput');
        }

        //Manual check - the McpTool's `security` attribute is not enforced by the MCP call pipeline (see Part.php)
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->security->isGranted('@labels.create_labels')) {
            throw new AccessDeniedException('Access denied: printing labels needs the "Create labels" permission.');
        }

        $type = LabelSupportedElement::tryFrom($data->targetType)
            ?? throw new BadRequestHttpException(sprintf('Unknown targetType "%s": use part, part_lot or storelocation.', $data->targetType));
        try {
            $ids = $this->agentJobCreator->normalizeIds($data->ids);
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException(sprintf('Invalid ids "%s": use database IDs and ranges like "1,2,5-10".', $data->ids));
        }
        $count = $this->agentJobCreator->countLabels($ids);
        if ($count > self::MAX_LABELS) {
            throw new BadRequestHttpException(sprintf('These ids make %d labels; print at most %d per call.', $count, self::MAX_LABELS));
        }

        $printers = $this->agentJobCreator->printersFor($user);
        if ($printers === []) {
            throw new BadRequestHttpException('You have no print agent with a printer yet. Set one up under Tools → Print agent in Part-DB.');
        }
        $profile = $this->profile($user, $type, $data->profileId);
        $printer = $this->printer($user, $profile, $printers, $data->printer);

        $job = $this->agentJobCreator->create($user, $printer['agent'], $printer['id'], $profile, $type, $ids);

        return CallToolResult::success([new TextContent(sprintf(
            'Sent %d label(s) for %s %s with label profile "%s" (id %d) to %s on agent "%s" (print job %d). %s',
            $count, $type->value, $ids, $profile->getName(), $profile->getID(), $printer['name'], $printer['agent']->getName(), $job->getID(),
            $printer['agent']->isOnline() ? 'The agent is online and prints it right away.' : 'The agent is offline; the job prints when it is back.',
        ))]);
    }

    private function profile(User $user, LabelSupportedElement $type, ?int $id): LabelProfile
    {
        if ($id !== null) {
            $profile = $this->entityManager->find(LabelProfile::class, $id);
            if (!$profile instanceof LabelProfile || !$this->security->isGranted('read', $profile)) {
                throw new NotFoundHttpException(sprintf('Label profile with id %d not found.', $id));
            }
            if ($profile->getOptions()->getSupportedElement() !== $type) {
                throw new BadRequestHttpException(sprintf('Label profile "%s" (id %d) is for %s, not %s.',
                    $profile->getName(), $id, $profile->getOptions()->getSupportedElement()->value, $type->value));
            }
            return $profile;
        }

        $profiles = array_values(array_filter(
            $this->entityManager->getRepository(LabelProfile::class)->findForSupportedElement($type),
            fn (LabelProfile $profile): bool => $this->security->isGranted('read', $profile),
        ));
        foreach ($profiles as $profile) {
            if ($this->agentJobCreator->defaultPrinterFor($user, $profile) !== null) {
                return $profile;
            }
        }
        if (count($profiles) === 1) {
            return $profiles[0];
        }
        if ($profiles === []) {
            throw new BadRequestHttpException(sprintf('There is no label profile for %s yet; create one in Part-DB first.', $type->value));
        }
        throw new BadRequestHttpException('No label profile for '.$type->value.' has a default printer; choose one with profileId: '
            .implode(', ', array_map(static fn (LabelProfile $p): string => sprintf('%d ("%s")', $p->getID(), $p->getName()), $profiles)).'.');
    }

    /**
     * @param list<array{agent: Agent, id: string, name: string}> $printers
     * @return array{agent: Agent, id: string, name: string}
     */
    private function printer(User $user, LabelProfile $profile, array $printers, ?string $wanted): array
    {
        if ($wanted === null || $wanted === '') {
            $default = $this->agentJobCreator->defaultPrinterFor($user, $profile);
            if ($default !== null) {
                return $default;
            }
            if (count($printers) === 1) {
                return $printers[0];
            }
            throw new BadRequestHttpException('The label profile has no default printer; choose a printer: '.$this->describe($printers).'.');
        }

        $matches = array_values(array_filter($printers,
            static fn (array $p): bool => $wanted === $p['agent']->getID().'|'.$p['id'] || $wanted === $p['id']));
        if (count($matches) === 1) {
            return $matches[0];
        }
        throw new BadRequestHttpException(sprintf('%s printer "%s"; use one of: %s.', $matches === [] ? 'Unknown' : 'Ambiguous', $wanted, $this->describe($printers)));
    }

    /**
     * @param list<array{agent: Agent, id: string, name: string}> $printers
     */
    private function describe(array $printers): string
    {
        return implode(', ', array_map(static fn (array $p): string => sprintf('"%d|%s" (%s on agent "%s")',
            $p['agent']->getID(), $p['id'], $p['name'], $p['agent']->getName()), $printers));
    }
}
