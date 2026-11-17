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

use ApiPlatform\Metadata\Get;
use App\Entity\AgentSystem\Agent;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\GetLabelPrintingOptionsInput;
use App\State\Mcp\GetLabelPrintingOptionsProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GetLabelPrintingOptionsProcessorTest extends WebTestCase
{
    public function testListsProfilesAndOnlyTheUsersOwnPrinters(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $users = $em->getRepository(User::class);
        foreach (['admin' => 'Own QL', 'user' => 'Foreign QL'] as $owner => $printerName) {
            $em->persist((new Agent())->setOwner($users->findOneBy(['name' => $owner]))
                ->setUuid(bin2hex(random_bytes(8)))->setName($owner.' pc')
                ->setPrinters([['id' => 'ql', 'name' => $printerName]])
                ->setLastSeenAt(new \DateTimeImmutable()));
        }
        $em->find(LabelProfile::class, 3)->setDefaultPrinter('ql');
        $em->flush();
        $client->loginUser($users->findOneBy(['name' => 'admin']));

        $result = self::getContainer()->get(GetLabelPrintingOptionsProcessor::class)->process(new GetLabelPrintingOptionsInput(), new Get());

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        $data = json_decode($result->content[0]->text, true, flags: JSON_THROW_ON_ERROR);

        $profiles = array_column($data['profiles'], null, 'id');
        self::assertSame(['id' => 3, 'name' => 'Profile 3', 'targetType' => 'part_lot', 'width' => $profiles[3]['width'],
            'height' => $profiles[3]['height'], 'defaultPrinter' => 'ql'], $profiles[3]);
        self::assertNull($profiles[1]['defaultPrinter']);
        self::assertSame('part', $profiles[1]['targetType']);

        self::assertCount(1, $data['printers'], "other users' printers are not listed");
        self::assertSame('Own QL', $data['printers'][0]['name']);
        self::assertSame('admin pc', $data['printers'][0]['agent']);
        self::assertTrue($data['printers'][0]['online']);
        self::assertMatchesRegularExpression('/^\d+\|ql$/', $data['printers'][0]['printer']);
    }

    public function testNeedsTheReadProfilesPermission(): void
    {
        $client = static::createClient();
        $client->loginUser(self::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['name' => 'noread']));

        $result = self::getContainer()->get(GetLabelPrintingOptionsProcessor::class)->process(new GetLabelPrintingOptionsInput(), new Get());

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
    }
}
