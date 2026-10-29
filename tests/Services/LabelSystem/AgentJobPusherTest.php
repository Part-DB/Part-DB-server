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

namespace App\Tests\Services\LabelSystem;

use App\Entity\AgentSystem\Agent;
use App\Entity\AgentSystem\AgentJob;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\Entity\UserSystem\User;
use App\Services\LabelSystem\AgentJobCreator;
use App\Services\LabelSystem\AgentJobPusher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

#[Group("DB")]
final class AgentJobPusherTest extends KernelTestCase
{
    private const PUBLIC_URL = 'http://127.0.0.1:3000/.well-known/mercure';

    /** @var list<Update> */
    private array $published = [];
    private bool $failPublish = false;

    private function pusher(?string $publicUrl = self::PUBLIC_URL): AgentJobPusher
    {
        $hub = new MockHub(self::PUBLIC_URL, new StaticTokenProvider('x'), function (Update $update): string {
            if ($this->failPublish) {
                throw new \RuntimeException('hub down');
            }
            $this->published[] = $update;
            return 'id';
        });
        return new AgentJobPusher(new NullLogger(), $publicUrl, '', 'test-secret-at-least-256-bits-long!!', 'https://localhost', $hub);
    }

    private function agent(): Agent
    {
        $agent = $this->createStub(Agent::class);
        $agent->method('getId')->willReturn(42);
        $agent->method('getUuid')->willReturn('6f1c2b8e-0d3a-4c55-9e1f-1a2b3c4d5e6f');
        return $agent;
    }

    private function job(): AgentJob
    {
        $job = $this->createStub(AgentJob::class);
        $job->method('getId')->willReturn(7);
        $job->method('getAgent')->willReturn($this->agent());
        return $job;
    }

    public function testDisabledWithoutPublicUrl(): void
    {
        $pusher = $this->pusher('');
        $this->assertFalse($pusher->isEnabled());
        $this->assertNull($pusher->subscription($this->agent()));
        $pusher->notify($this->job());
        $this->assertSame([], $this->published);
    }

    public function testNotifyPublishesPrivateUpdate(): void
    {
        $this->pusher()->notify($this->job());

        $this->assertCount(1, $this->published);
        $this->assertSame(['urn:part-db:agent:42:jobs'], $this->published[0]->getTopics());
        $this->assertTrue($this->published[0]->isPrivate());
        $this->assertSame(['job_id' => 7], json_decode($this->published[0]->getData(), true));
    }

    public function testPublishFailureDoesNotBreakJobCreation(): void
    {
        $this->failPublish = true;
        $job = $this->createJob();

        $this->assertNotNull($job->getId());
    }

    public function testCreateAndResendNotify(): void
    {
        $job = $this->createJob();
        static::getContainer()->get(AgentJobCreator::class)->resend($job);

        $this->assertCount(2, $this->published);
    }

    private function createJob(): AgentJob
    {
        self::bootKernel();
        $container = static::getContainer();
        $container->set(AgentJobPusher::class, $this->pusher());
        $em = $container->get(EntityManagerInterface::class);
        $admin = $em->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $agent = (new Agent())->setUuid('0b7c9f5e-2a4d-4e8f-9c1b-3d5e7f9a1b2c')->setOwner($admin)->setPrinters([['id' => 'ql810']]);
        $em->persist($agent);
        $em->flush();
        $profile = $em->getRepository(LabelProfile::class)->findOneBy([]);

        return $container->get(AgentJobCreator::class)->create($admin, $agent, 'ql810', $profile, LabelSupportedElement::PART, '1');
    }
}
