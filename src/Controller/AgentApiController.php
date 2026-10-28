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

namespace App\Controller;

use App\Entity\AgentSystem\Agent;
use App\Entity\AgentSystem\AgentJob;
use App\Entity\AgentSystem\AgentJobStatus;
use App\Entity\UserSystem\User;
use App\Repository\AgentJobRepository;
use App\Security\ApiTokenAuthenticatedToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * JSON API for agents (e.g. label print agents) that fetch jobs from Part-DB. An agent authenticates with an API
 * token of its owner (read-only is enough) and only ever sees its owner's agents and jobs. See docs/usage/labels.md.
 */
#[Route('/agent-api/v1')]
class AgentApiController extends AbstractController
{
    public const POLL_SECONDS = 30;
    private const MAX_PRINTERS = 50;
    private const MAX_STRING = 255;

    public function __construct(private readonly EntityManagerInterface $em, private readonly Security $security)
    {
    }

    #[Route('/agents/{uuid}/heartbeat', name: 'agent_api_heartbeat', requirements: ['uuid' => '[0-9a-fA-F-]{8,36}'], methods: ['POST'])]
    public function heartbeat(string $uuid, Request $request): JsonResponse
    {
        $user = $this->agentUser();
        $body = $this->body($request);
        $printers = $body['printers'] ?? [];
        if (!is_array($printers) || !array_is_list($printers) || count($printers) > self::MAX_PRINTERS) {
            throw new BadRequestHttpException('printers must be a list of at most '.self::MAX_PRINTERS.' objects');
        }
        foreach ($printers as $printer) {
            if (!is_array($printer) || !$this->isShortString($printer['id'] ?? null) || $printer['id'] === ''
                || (isset($printer['name']) && !$this->isShortString($printer['name']))) {
                throw new BadRequestHttpException('each printer needs a string id (and an optional string name) of at most '.self::MAX_STRING.' characters');
            }
        }
        foreach (['name', 'version'] as $field) {
            if (isset($body[$field]) && !$this->isShortString($body[$field], $field === 'version' ? 64 : self::MAX_STRING)) {
                throw new BadRequestHttpException($field.' is too long or not a string');
            }
        }

        $agent = $this->em->getRepository(Agent::class)->findOneBy(['owner' => $user, 'uuid' => $uuid]);
        if (!$agent instanceof Agent) {
            $agent = (new Agent())->setOwner($user)->setUuid($uuid);
            $this->em->persist($agent);
        }
        $agent->setName($body['name'] ?? '')
            ->setVersion($body['version'] ?? '')
            ->setPrinters($printers)
            ->setLastSeenAt(new \DateTimeImmutable());
        $this->em->flush();

        return new JsonResponse(['agent_id' => $agent->getId(), 'poll_seconds' => self::POLL_SECONDS]);
    }

    #[Route('/agents/{uuid}/jobs', name: 'agent_api_jobs', requirements: ['uuid' => '[0-9a-fA-F-]{8,36}'], methods: ['GET'])]
    public function jobs(string $uuid): JsonResponse
    {
        $agent = $this->em->getRepository(Agent::class)->findOneBy(['owner' => $this->agentUser(), 'uuid' => $uuid]);
        if (!$agent instanceof Agent) {
            throw new NotFoundHttpException('Unknown agent, send a heartbeat first');
        }
        $agent->setLastSeenAt(new \DateTimeImmutable());
        $this->em->flush();

        $jobs = $this->em->getRepository(AgentJob::class)->findBy(
            ['agent' => $agent, 'status' => AgentJobStatus::PENDING], ['id' => 'ASC'], 20);

        return new JsonResponse(array_map($this->jobJson(...), $jobs));
    }

    #[Route('/jobs/{id}/claim', name: 'agent_api_job_claim', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function claim(int $id): JsonResponse
    {
        $job = $this->ownJob($id);
        /** @var AgentJobRepository $repo */
        $repo = $this->em->getRepository(AgentJob::class);
        if (!$repo->claim($job)) {
            throw new ConflictHttpException('The job is not pending anymore');
        }

        return new JsonResponse($this->jobJson($job));
    }

    #[Route('/jobs/{id}/result', name: 'agent_api_job_result', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function result(int $id, Request $request): Response
    {
        $job = $this->ownJob($id);
        $body = $this->body($request);
        $status = AgentJobStatus::tryFrom((string) ($body['status'] ?? ''));
        if ($status !== AgentJobStatus::DONE && $status !== AgentJobStatus::FAILED) {
            throw new BadRequestHttpException('status must be "done" or "failed"');
        }
        $message = $body['message'] ?? '';
        if (!is_string($message)) {
            throw new BadRequestHttpException('message must be a string');
        }
        if ($job->getStatus() !== AgentJobStatus::CLAIMED) {
            throw new ConflictHttpException('Only claimed jobs can get a result');
        }
        $job->setStatus($status)->setMessage(mb_substr($message, 0, 2000));
        $this->em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function agentUser(): User
    {
        //Agents always use API tokens. Rejecting browser sessions means other websites can't make a logged-in
        //browser register agents or finish jobs (cross-site requests carry the session cookie).
        if (!$this->security->getToken() instanceof ApiTokenAuthenticatedToken) {
            throw $this->createAccessDeniedException('Agents must authenticate with an API token');
        }
        $this->denyAccessUnlessGranted('@labels.create_labels');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        return $user;
    }

    /**
     * Returns a job of one of the current user's agents. Other users' jobs are reported as missing, so ids don't leak.
     */
    private function ownJob(int $id): AgentJob
    {
        $user = $this->agentUser();
        $job = $this->em->find(AgentJob::class, $id);
        if (!$job instanceof AgentJob || $job->getAgent()?->getOwner() !== $user) {
            throw new NotFoundHttpException('Unknown job');
        }
        return $job;
    }

    private function body(Request $request): array
    {
        if ($request->getContent() === '') {
            return [];
        }
        try {
            $body = json_decode($request->getContent(), true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON');
        }
        if (!is_array($body)) {
            throw new BadRequestHttpException('Expected a JSON object');
        }
        return $body;
    }

    private function isShortString(mixed $value, int $max = self::MAX_STRING): bool
    {
        return is_string($value) && mb_strlen($value) <= $max;
    }

    private function jobJson(AgentJob $job): array
    {
        return [
            'id' => $job->getId(),
            'type' => $job->getType(),
            'printer' => $job->getPrinter(),
            'payload' => $job->getPayload(),
            'created_at' => $job->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
