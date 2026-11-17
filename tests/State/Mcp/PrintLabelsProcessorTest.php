<?php

declare(strict_types=1);

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

namespace App\Tests\State\Mcp;

use ApiPlatform\Metadata\Post;
use App\Entity\AgentSystem\Agent;
use App\Entity\AgentSystem\AgentJob;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\PrintLabelsInput;
use App\State\Mcp\PrintLabelsProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PrintLabelsProcessorTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->loginAs('admin');
    }

    private function loginAs(string $name): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['name' => $name]);
        $this->client->loginUser($user);
        return $user;
    }

    /**
     * An agent of the given user reporting one printer "fake" ("Fake QL").
     */
    private function agent(string $owner = 'admin', string $printer = 'fake'): Agent
    {
        $agent = (new Agent())->setOwner($this->em->getRepository(User::class)->findOneBy(['name' => $owner]))
            ->setUuid(bin2hex(random_bytes(8)))->setName($owner.' pc')
            ->setPrinters([['id' => $printer, 'name' => 'Fake QL']])
            ->setLastSeenAt(new \DateTimeImmutable());
        $this->em->persist($agent);
        $this->em->flush();
        return $agent;
    }

    private function setDefaultPrinter(int $profileId, string $printer): void
    {
        $this->em->find(LabelProfile::class, $profileId)->setDefaultPrinter($printer);
        $this->em->flush();
    }

    private function print(PrintLabelsInput $input): CallToolResult
    {
        $result = self::getContainer()->get(PrintLabelsProcessor::class)->process($input, new Post());
        self::assertInstanceOf(CallToolResult::class, $result);
        return $result;
    }

    private function text(CallToolResult $result): string
    {
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        return $result->content[0]->text;
    }

    private function assertError(CallToolResult $result, string ...$contains): void
    {
        self::assertTrue($result->isError, 'expected an error, got: '.$this->text($result));
        foreach ($contains as $needle) {
            self::assertStringContainsString($needle, $this->text($result));
        }
        self::assertCount(0, $this->jobs(), 'an error must not create a print job');
    }

    /** @return list<AgentJob> */
    private function jobs(): array
    {
        $this->em->clear();
        return $this->em->getRepository(AgentJob::class)->findAll();
    }

    public function testPrintsWithTheProfileThatHasADefaultPrinter(): void
    {
        $agent = $this->agent();
        //Profile 3 is the fixtures' only part lot profile
        $this->setDefaultPrinter(3, 'fake');

        $result = $this->print(new PrintLabelsInput(targetType: 'part_lot', ids: '1, 2'));

        self::assertFalse($result->isError, $this->text($result));
        self::assertStringContainsString('2 label(s)', $this->text($result));
        self::assertStringContainsString('Fake QL', $this->text($result));
        self::assertStringContainsString('Profile 3', $this->text($result));
        $jobs = $this->jobs();
        self::assertCount(1, $jobs);
        self::assertSame($agent->getId(), $jobs[0]->getAgent()->getId());
        self::assertSame('fake', $jobs[0]->getPrinter());
        self::assertSame(['profile_id' => 3, 'element_type' => 'part_lot', 'element_ids' => '1,2'], $jobs[0]->getPayload());
    }

    public function testPrintsWithAGivenProfileAndPrinter(): void
    {
        $agent = $this->agent();

        $result = $this->print(new PrintLabelsInput(targetType: 'part', ids: '1', profileId: 4, printer: $agent->getId().'|fake'));

        self::assertFalse($result->isError, $this->text($result));
        self::assertSame(4, $this->jobs()[0]->getPayload()['profile_id']);
    }

    public function testThePrinterIdAloneIsEnoughWhenItIsUnique(): void
    {
        $this->agent();

        $result = $this->print(new PrintLabelsInput(targetType: 'part', ids: '1', profileId: 1, printer: 'fake'));

        self::assertFalse($result->isError, $this->text($result));
        self::assertSame('fake', $this->jobs()[0]->getPrinter());
    }

    public function testTheOnlyPrinterIsUsedWithoutADefault(): void
    {
        $this->agent();

        $result = $this->print(new PrintLabelsInput(targetType: 'part', ids: '1', profileId: 1));

        self::assertFalse($result->isError, $this->text($result));
        self::assertCount(1, $this->jobs());
    }

    public function testRefusesMoreThan50Labels(): void
    {
        $this->agent();
        $this->setDefaultPrinter(3, 'fake');

        $this->assertError($this->print(new PrintLabelsInput(targetType: 'part_lot', ids: '1-51')), '50');
    }

    public function testWithoutAnAgentSaysSo(): void
    {
        $this->assertError($this->print(new PrintLabelsInput(targetType: 'part', ids: '1', profileId: 1)), 'print agent');
    }

    public function testAProfileForAnotherTypeIsRejected(): void
    {
        $this->agent();

        $this->assertError($this->print(new PrintLabelsInput(targetType: 'part_lot', ids: '1', profileId: 1)), 'Profile 1', 'part_lot');
    }

    public function testWithoutADefaultPrinterItAsksWhichProfile(): void
    {
        $this->agent();

        //The fixtures have three part profiles, none with a default printer
        $this->assertError($this->print(new PrintLabelsInput(targetType: 'part', ids: '1')), 'profileId', 'Profile 1', 'Profile 4');
    }

    public function testWithSeveralPrintersAndNoDefaultItAsksWhichPrinter(): void
    {
        $this->agent();
        $this->agent(printer: 'other');

        $this->assertError($this->print(new PrintLabelsInput(targetType: 'part', ids: '1', profileId: 1)), 'printer', 'fake', 'other');
    }

    public function testAnotherUsersPrinterIsRejected(): void
    {
        $foreign = $this->agent('user');
        $this->agent();

        $this->assertError($this->print(new PrintLabelsInput(targetType: 'part', ids: '1', profileId: 1, printer: $foreign->getId().'|fake')), 'printer');
    }

    public function testInvalidIdsAreRejected(): void
    {
        $this->agent();

        $this->assertError($this->print(new PrintLabelsInput(targetType: 'part', ids: 'abc', profileId: 1)), 'ids');
    }

    public function testNeedsTheCreateLabelsPermission(): void
    {
        $this->agent('noread');
        $this->loginAs('noread');

        $this->assertError($this->print(new PrintLabelsInput(targetType: 'part', ids: '1', profileId: 1)), 'denied');
    }
}
