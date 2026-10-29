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

namespace App\Tests\Entity\AgentSystem;

use App\Entity\AgentSystem\Agent;
use App\Entity\AgentSystem\AgentJob;
use App\Entity\AgentSystem\AgentJobStatus;
use PHPUnit\Framework\TestCase;

final class AgentJobTest extends TestCase
{
    public function testAgentOnline(): void
    {
        $now = new \DateTimeImmutable();
        $agent = new Agent();
        $this->assertFalse($agent->isOnline($now));
        $agent->setLastSeenAt($now->modify('-60 seconds'));
        $this->assertTrue($agent->isOnline($now));
        $agent->setLastSeenAt($now->modify('-200 seconds'));
        $this->assertFalse($agent->isOnline($now));
    }

    public function testAgentPrinters(): void
    {
        $agent = new Agent();
        $agent->setPrinters([['id' => 'ql810', 'name' => 'Brother QL-810W'], ['id' => 'zebra']]);
        $this->assertTrue($agent->hasPrinter('ql810'));
        $this->assertFalse($agent->hasPrinter('x'));
        $this->assertSame('Brother QL-810W', $agent->getPrinterName('ql810'));
        $this->assertSame('zebra', $agent->getPrinterName('zebra'));
        $this->assertSame('x', $agent->getPrinterName('x'));
    }

    public function testJobStale(): void
    {
        $now = new \DateTimeImmutable();
        $job = new AgentJob();
        $this->assertSame(AgentJobStatus::PENDING, $job->getStatus());
        $job->setUpdatedAt($now->modify('-601 seconds'));
        $this->assertFalse($job->isStale($now), 'pending jobs are never stale');
        $job->setStatus(AgentJobStatus::CLAIMED); //refreshes updatedAt
        $this->assertFalse($job->isStale($now));
        $job->setUpdatedAt($now->modify('-601 seconds'));
        $this->assertTrue($job->isStale($now));
        $job->setUpdatedAt($now->modify('-60 seconds'));
        $this->assertFalse($job->isStale($now));
    }
}
