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

namespace App\Services\LabelSystem;

use App\Entity\AgentSystem\Agent;
use App\Entity\AgentSystem\AgentJob;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\Entity\UserSystem\User;
use App\Repository\AgentJobRepository;
use App\Services\Misc\RangeParser;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sends label print jobs to the print agents of a user.
 */
final readonly class AgentJobCreator
{
    public function __construct(private EntityManagerInterface $em, private RangeParser $rangeParser, private AgentJobPusher $pusher)
    {
    }

    /**
     * The printers of the user's agents, most recently seen agent first.
     * @return list<array{agent: Agent, id: string, name: string}>
     */
    public function printersFor(User $user): array
    {
        $agents = $this->em->getRepository(Agent::class)->findBy(['owner' => $user], ['lastSeenAt' => 'DESC']);
        $printers = [];
        foreach ($agents as $agent) {
            foreach ($agent->getPrinters() as $printer) {
                $printers[] = ['agent' => $agent, 'id' => $printer['id'], 'name' => $agent->getPrinterName($printer['id'])];
            }
        }
        return $printers;
    }

    /**
     * The printer of the user's agents that the profile names as its default printer, or null.
     * @return array{agent: Agent, id: string, name: string}|null
     */
    public function defaultPrinterFor(User $user, LabelProfile $profile): ?array
    {
        if ($profile->getDefaultPrinter() === '') {
            return null;
        }
        foreach ($this->printersFor($user) as $printer) {
            if ($printer['id'] === $profile->getDefaultPrinter()) {
                return $printer;
            }
        }
        return null;
    }

    /**
     * Normalises ids from the label generator ("1, 2;5 - 10", empty entries) to "1,2,5-10".
     * @throws \InvalidArgumentException if the ids are not a valid range
     */
    public function normalizeIds(string $ids): string
    {
        $ids = implode(',', array_filter(preg_split('/[,;]/', preg_replace('/\s+/', '', $ids)), static fn(string $s) => $s !== ''));
        if ($ids === '' || !$this->rangeParser->isValidRange($ids)) {
            throw new \InvalidArgumentException('Invalid element ids');
        }
        return $ids;
    }

    public function countLabels(string $ids): int
    {
        return count($this->rangeParser->parse($ids));
    }

    /**
     * Creates a pending print job. Also deletes finished jobs older than AgentJob::KEEP_DAYS.
     * @throws \InvalidArgumentException if the agent doesn't belong to the user or doesn't have the printer, or the ids are invalid
     */
    public function create(User $user, Agent $agent, string $printer, LabelProfile $profile, LabelSupportedElement $elementType, string $ids): AgentJob
    {
        if ($agent->getOwner() !== $user || !$agent->hasPrinter($printer)) {
            throw new \InvalidArgumentException('Unknown printer');
        }
        $job = (new AgentJob())
            ->setAgent($agent)
            ->setPrinter($printer)
            ->setCreatedBy($user)
            ->setPayload([
                'profile_id' => $profile->getID(),
                'element_type' => $elementType->value,
                'element_ids' => $this->normalizeIds($ids),
            ]);

        return $this->save($job);
    }

    /**
     * Sends the same job again (as a new job, so the history stays intact).
     */
    public function resend(AgentJob $job): AgentJob
    {
        return $this->save((new AgentJob())
            ->setAgent($job->getAgent())
            ->setPrinter($job->getPrinter())
            ->setType($job->getType())
            ->setCreatedBy($job->getCreatedBy())
            ->setPayload($job->getPayload()));
    }

    private function save(AgentJob $job): AgentJob
    {
        /** @var AgentJobRepository<AgentJob> $repo */
        $repo = $this->em->getRepository(AgentJob::class);
        $repo->pruneFinished(new \DateTimeImmutable('-'.AgentJob::KEEP_DAYS.' days'));
        $this->em->persist($job);
        $this->em->flush();
        $this->pusher->notify($job);

        return $job;
    }
}
