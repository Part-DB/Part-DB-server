<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Services\Parts;

use App\Entity\AgentSystem\Agent;
use App\Entity\AgentSystem\AgentJob;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use App\Services\Parts\PartsTableActionHandler;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class PartsTableActionHandlerTest extends WebTestCase
{
    private PartsTableActionHandler $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(PartsTableActionHandler::class);
    }

    public function testExportActionsRedirectToExportController(): void
    {
        // Mock a Part entity with required properties
        $part = $this->createMock(Part::class);
        $part->method('getId')->willReturn(1);
        $part->method('getName')->willReturn('Test Part');

        $selected_parts = [$part];

        // Test each export format, focusing on our new xlsx format
        $formats = ['json', 'csv', 'xml', 'yaml', 'xlsx'];

        foreach ($formats as $format) {
            $action = "export_{$format}";
            $result = $this->service->handleAction($action, $selected_parts, 1, '/test');

            $this->assertInstanceOf(RedirectResponse::class, $result);
            $this->assertStringContainsString('parts/export', $result->getTargetUrl());
            $this->assertStringContainsString("format={$format}", $result->getTargetUrl());
        }
    }

    public function testExportUrlContainsPartIds(): void
    {
        $part1 = $this->createMock(Part::class);
        $part1->method('getId')->willReturn(42);

        $part2 = $this->createMock(Part::class);
        $part2->method('getId')->willReturn(99);

        $result = $this->service->handleAction('export_csv', [$part1, $part2], 1, '/test');

        $this->assertInstanceOf(RedirectResponse::class, $result);
        // Commas in query-string values are not percent-encoded by Symfony's UrlGenerator
        $this->assertStringContainsString('ids=42,99', $result->getTargetUrl());
    }

    public function testExportCsvEscaped(): void
    {
        $result = $this->service->handleAction('export_csv_escaped', [], 1, '/test');
        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('format=csv_escaped', $result->getTargetUrl());
    }

    public function testExportWithNoPartsProducesEmptyIds(): void
    {
        $result = $this->service->handleAction('export_json', [], 1, '/test');

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('parts/export', $result->getTargetUrl());
        // ids parameter present but empty
        $this->assertStringContainsString('ids=', $result->getTargetUrl());
    }

    public function testUnknownActionWithEmptyPartsReturnsNull(): void
    {
        // The unknown-action switch only runs inside the foreach loop, so an
        // empty parts list means the loop body never executes and no exception is thrown.
        $result = $this->service->handleAction('unknown_action_xyz', [], null, '/test');
        $this->assertNull($result);
    }

    /**
     * Logs in admin, gives the request a session (for the flash) and returns the parts with ids 1 and 2.
     * @return list<Part>
     */
    private function loginAndGetParts(): array
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $admin = $em->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($admin, 'main', $admin->getRoles()));
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get(RequestStack::class)->push($request);
        return [$em->find(Part::class, 1), $em->find(Part::class, 2)];
    }

    public function testPrintLabelSendsAJobToTheDefaultPrinter(): void
    {
        $parts = $this->loginAndGetParts();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $agent = (new Agent())->setOwner($em->getRepository(User::class)->findOneBy(['name' => 'admin']))
            ->setUuid('table-test-agent')->setPrinters([['id' => 'fake']])->setLastSeenAt(new \DateTimeImmutable());
        $em->persist($agent);
        $em->find(LabelProfile::class, 1)->setDefaultPrinter('fake');
        $em->flush();

        $result = $this->service->handleAction('print_label', $parts, 1, '/en/parts');

        $this->assertSame('/en/parts', $result?->getTargetUrl());
        $jobs = $em->getRepository(AgentJob::class)->findAll();
        $this->assertCount(1, $jobs);
        $this->assertSame(['profile_id' => 1, 'element_type' => 'part', 'element_ids' => '1,2'], $jobs[0]->getPayload());
        $this->assertNotEmpty(self::getContainer()->get(RequestStack::class)->getSession()->getFlashBag()->peek('success'));
    }

    public function testPrintLabelWithoutAgentPrinterOpensTheDialog(): void
    {
        $parts = $this->loginAndGetParts();

        $result = $this->service->handleAction('print_label', $parts, 1, '/en/parts');

        $this->assertStringContainsString('/label/1/dialog', $result?->getTargetUrl() ?? '');
        $this->assertStringContainsString('generate=1', $result->getTargetUrl());
        $this->assertCount(0, self::getContainer()->get(EntityManagerInterface::class)->getRepository(AgentJob::class)->findAll());
    }
}
