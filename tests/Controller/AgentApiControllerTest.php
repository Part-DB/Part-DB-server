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

namespace App\Tests\Controller;

use App\DataFixtures\APITokenFixtures;
use App\Entity\AgentSystem\Agent;
use App\Entity\AgentSystem\AgentJob;
use App\Entity\AgentSystem\AgentJobStatus;
use App\Entity\UserSystem\User;
use App\Services\LabelSystem\AgentJobPusher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group("slow")]
#[Group("DB")]
final class AgentApiControllerTest extends WebTestCase
{
    private const BASE_URL = '/en/agent-api/v1';
    private const UUID = '6f1c2b8e-0d3a-4c55-9e1f-1a2b3c4d5e6f';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient([], [
            'HTTP_AUTHORIZATION' => 'Bearer '.APITokenFixtures::TOKEN_READONLY, //read-only token of "admin"
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function json(string $method, string $url, ?array $body = null): array
    {
        $this->client->request($method, self::BASE_URL.$url, server: ['CONTENT_TYPE' => 'application/json'],
            content: $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR));
        $content = $this->client->getResponse()->getContent();
        return $content === '' || $content === false ? [] : (json_decode($content, true) ?? []);
    }

    private function heartbeat(array $printers = [['id' => 'ql810', 'name' => 'Brother QL-810W']]): array
    {
        return $this->json('POST', '/agents/'.self::UUID.'/heartbeat', ['name' => 'bench pc', 'version' => '0.2.0', 'printers' => $printers]);
    }

    private function user(string $name): User
    {
        return $this->em->getRepository(User::class)->findOneBy(['name' => $name]);
    }

    private function job(Agent $agent): AgentJob
    {
        $job = (new AgentJob())->setAgent($agent)->setPrinter('ql810')
            ->setPayload(['profile_id' => 1, 'element_type' => 'part', 'element_ids' => '1,2']);
        $this->em->persist($job);
        $this->em->flush();
        return $job;
    }

    public function testHeartbeatCreatesAndUpdatesAgent(): void
    {
        $first = $this->heartbeat();
        $this->assertResponseIsSuccessful();
        $this->assertSame(30, $first['poll_seconds']);

        $second = $this->heartbeat([['id' => 'ql810'], ['id' => 'zebra', 'name' => 'ZD421']]);
        $this->assertSame($first['agent_id'], $second['agent_id']);

        $agent = $this->em->find(Agent::class, $second['agent_id']);
        $this->assertSame('admin', $agent->getOwner()->getName());
        $this->assertSame('bench pc', $agent->getName());
        $this->assertSame('0.2.0', $agent->getVersion());
        $this->assertTrue($agent->hasPrinter('zebra'));
        $this->assertTrue($agent->isOnline());
    }

    public function testHeartbeatOmitsMercureWhenDisabled(): void
    {
        $this->assertArrayNotHasKey('mercure', $this->heartbeat());
    }

    public function testHeartbeatOffersMercureWhenEnabled(): void
    {
        $hub = 'http://127.0.0.1:3000/.well-known/mercure';
        static::getContainer()->set(AgentJobPusher::class, new AgentJobPusher(new NullLogger(), $hub, 'http://mercure:3000/.well-known/mercure',
            'test-secret-at-least-256-bits-long!!', 'https://localhost'));

        $reply = $this->heartbeat();
        $topic = 'urn:part-db:agent:'.$reply['agent_id'].':jobs';
        $this->assertSame($hub, $reply['mercure']['hub']);
        $this->assertSame($topic, $reply['mercure']['topic']);

        [$header, $payload] = array_map(static fn(string $part): array => json_decode(base64_decode(strtr($part, '-_', '+/')), true),
            array_slice(explode('.', $reply['mercure']['token']), 0, 2));
        $this->assertSame('at+jwt', $header['typ']);
        $this->assertSame(self::UUID, $payload['sub']);
        $this->assertSame('https://localhost', $payload['iss']);
        $this->assertContains($hub, (array) $payload['aud']);
        $this->assertEqualsWithDelta(time() + 3600, $payload['exp'], 60);
        $this->assertSame([['type' => 'https://mercure.rocks/authorization-detail', 'actions' => ['subscribe'], 'topics' => [['match' => $topic]]]],
            $payload['authorization_details']);
    }

    #[TestWith([''])]
    #[TestWith(['too-short'])]
    public function testHeartbeatWorksWithMisconfiguredMercure(string $secret): void
    {
        static::getContainer()->set(AgentJobPusher::class, new AgentJobPusher(new NullLogger(), 'http://127.0.0.1:3000/.well-known/mercure',
            '', $secret, 'https://localhost'));

        $reply = $this->heartbeat();
        $this->assertResponseIsSuccessful();
        $this->assertArrayNotHasKey('mercure', $reply);
    }

    public function testJobLifecycle(): void
    {
        $agent = $this->em->find(Agent::class, $this->heartbeat()['agent_id']);
        $job = $this->job($agent);

        $jobs = $this->json('GET', '/agents/'.self::UUID.'/jobs');
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $jobs);
        $this->assertSame($job->getId(), $jobs[0]['id']);
        $this->assertSame('print', $jobs[0]['type']);
        $this->assertSame('ql810', $jobs[0]['printer']);
        $this->assertSame(['profile_id' => 1, 'element_type' => 'part', 'element_ids' => '1,2'], $jobs[0]['payload']);

        $this->assertSame($job->getId(), $this->json('POST', '/jobs/'.$job->getId().'/claim')['id']);
        $this->assertResponseStatusCodeSame(200);
        $this->json('POST', '/jobs/'.$job->getId().'/claim');
        $this->assertResponseStatusCodeSame(409, 'a job can only be claimed once');
        $this->assertSame([], $this->json('GET', '/agents/'.self::UUID.'/jobs'), 'claimed jobs are no longer pending');

        $this->json('POST', '/jobs/'.$job->getId().'/result', ['status' => 'done', 'message' => '2 page(s) printed']);
        $this->assertResponseStatusCodeSame(204);
        $this->json('POST', '/jobs/'.$job->getId().'/result', ['status' => 'failed']);
        $this->assertResponseStatusCodeSame(409);

        $this->em->clear();
        $job = $this->em->find(AgentJob::class, $job->getId());
        $this->assertSame(AgentJobStatus::DONE, $job->getStatus());
        $this->assertSame('2 page(s) printed', $job->getMessage());
    }

    public function testOtherUsersAgentsAndJobsAreInvisible(): void
    {
        $agent = (new Agent())->setOwner($this->user('user'))->setUuid(self::UUID)->setName('other');
        $this->em->persist($agent);
        $job = $this->job($agent);

        $this->json('GET', '/agents/'.self::UUID.'/jobs');
        $this->assertResponseStatusCodeSame(404, 'jobs of an agent the token user never registered');
        $this->json('POST', '/jobs/'.$job->getId().'/claim');
        $this->assertResponseStatusCodeSame(404);
        $this->json('POST', '/jobs/'.$job->getId().'/result', ['status' => 'done']);
        $this->assertResponseStatusCodeSame(404);

        //Registering the same uuid with the admin token creates a separate agent
        $this->heartbeat();
        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->json('GET', '/agents/'.self::UUID.'/jobs'));
    }

    public function testInvalidHeartbeatsAreRejected(): void
    {
        foreach ([
            'printers not a list' => ['name' => 'x', 'printers' => 'x'],
            'printer without id' => ['name' => 'x', 'printers' => [['name' => 'y']]],
            'printer id too long' => ['name' => 'x', 'printers' => [['id' => str_repeat('a', 256)]]],
            'too many printers' => ['name' => 'x', 'printers' => array_fill(0, 51, ['id' => 'p'])],
            'name too long' => ['name' => str_repeat('a', 256), 'printers' => []],
            'huge printer details' => ['name' => 'x', 'printers' => [['id' => 'p', 'junk' => str_repeat('a', 70000)]]],
        ] as $case => $body) {
            $this->json('POST', '/agents/'.self::UUID.'/heartbeat', $body);
            $this->assertResponseStatusCodeSame(400, $case);
        }
        $this->json('POST', '/agents/not!a!uuid/heartbeat', ['name' => 'x', 'printers' => []]);
        $this->assertResponseStatusCodeSame(404, 'bad uuid');
        $this->json('POST', '/jobs/1/result', ['status' => 'maybe']);
        $this->assertContains($this->client->getResponse()->getStatusCode(), [400, 404]);
    }

    public function testTokenIsRequired(): void
    {
        $this->client->setServerParameter('HTTP_AUTHORIZATION', '');
        $this->heartbeat();
        $this->assertResponseStatusCodeSame(401);
    }

    public function testBrowserSessionsCannotUseTheApi(): void
    {
        //Only API tokens: a logged-in browser must not be driven cross-site into creating agents or finishing jobs
        $this->client->setServerParameter('HTTP_AUTHORIZATION', '');
        $this->client->loginUser($this->user('admin'));
        $this->heartbeat();
        $this->assertResponseStatusCodeSame(403);
    }
}
