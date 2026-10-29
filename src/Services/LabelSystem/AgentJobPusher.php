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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\FrankenPhpHub;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\DefaultClaimsTokenFactory;
use Symfony\Component\Mercure\Jwt\FactoryTokenProvider;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Update;

/**
 * Tells print agents about new jobs right away through a Mercure hub, so they don't have to wait for their next poll.
 * Off unless MERCURE_PUBLIC_URL is set; agents then just poll.
 */
final class AgentJobPusher
{
    /** Lifetime of the tokens agents subscribe with. They get a new one with every heartbeat. */
    public const TOKEN_SECONDS = 3600;

    public function __construct(
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'default::MERCURE_PUBLIC_URL')] private readonly ?string $publicUrl,
        #[Autowire(env: 'default::MERCURE_URL')] private readonly ?string $url,
        #[Autowire(env: 'default::MERCURE_JWT_SECRET')] private readonly ?string $secret,
        #[Autowire(env: 'default::MERCURE_JWT_ISSUER')] private readonly ?string $issuer,
        private ?HubInterface $hub = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->publicUrl !== null && $this->publicUrl !== '';
    }

    public function topic(Agent $agent): string
    {
        // Not based on Part-DB's URL: a job created via one host name must reach an agent connected via another.
        return 'urn:part-db:agent:'.$agent->getId().':jobs';
    }

    /**
     * Publishes a private update for the job's agent. Failures are only logged: the agent still finds the job when it polls.
     */
    public function notify(AgentJob $job): void
    {
        $agent = $job->getAgent();
        if (!$this->isEnabled() || $agent === null) {
            return;
        }
        try {
            $this->hub()->publish(new Update($this->topic($agent), json_encode(['job_id' => $job->getId()], JSON_THROW_ON_ERROR), private: true));
        } catch (\Throwable $e) {
            $this->logger->warning('Could not publish print job to the Mercure hub: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * What the agent needs to subscribe to its topic, or null if push is off.
     * @return array{hub: string, topic: string, token: string}|null
     */
    public function subscription(Agent $agent): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }
        $topic = $this->topic($agent);
        $token = $this->hub()->getFactory()?->create([new Grant([Grant::ACTION_SUBSCRIBE], [$topic])], ['sub' => $agent->getUuid()]);

        return ['hub' => (string) $this->publicUrl, 'topic' => $topic, 'token' => (string) $token];
    }

    private function hub(): HubInterface
    {
        if ($this->hub === null) {
            if ($this->secret === null || $this->secret === '') {
                throw new \LogicException('MERCURE_JWT_SECRET must be set when MERCURE_PUBLIC_URL is set');
            }
            $publicUrl = (string) $this->publicUrl;
            $claims = ['iss' => (string) $this->issuer, 'aud' => $publicUrl, 'sub' => 'part-db', 'client_id' => 'part-db'];
            $factory = new DefaultClaimsTokenFactory(new LcobucciFactory($this->secret, jwtLifetime: self::TOKEN_SECONDS), $claims);
            if ($this->url === null || $this->url === '') {
                // FrankenPHP's built-in hub
                $this->hub = new FrankenPhpHub($publicUrl, $factory);
            } else {
                // The publisher token goes to MERCURE_URL, which the hub may know under another name than the public one.
                $publisher = new FactoryTokenProvider($factory, [new Grant([Grant::ACTION_PUBLISH], ['*'])], ['aud' => [$publicUrl, $this->url]]);
                $this->hub = new Hub($this->url, $publisher, $factory, $publicUrl);
            }
        }

        return $this->hub;
    }
}
