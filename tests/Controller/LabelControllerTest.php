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

use App\Entity\UserSystem\User;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group("slow")]
#[Group("DB")]
final class LabelControllerTest extends WebTestCase
{
    private const PRINT_BUTTON = '[data-controller="pages--print-agent-print"]';

    private function loginAsAdmin($client): void
    {
        $user = $client->getContainer()->get('doctrine')->getManager()
            ->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $client->loginUser($user);
    }

    public function testDialogWithProfileHasPrintButton(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', '/en/label/1/dialog?target_id=1&target_type=part&generate=1');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(self::PRINT_BUTTON
            .'[data-pages--print-agent-print-profile-value="1"]'
            .'[data-pages--print-agent-print-ids-value="1"]'
            .'[data-pages--print-agent-print-type-value="part"]');
    }

    public function testDialogWithoutProfileHasNoPrintButton(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', '/en/label/dialog?target_id=1&target_type=part');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists(self::PRINT_BUTTON);
    }

    public function testPartPageLabelMenuHasPrintIcons(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', '/en/part/1');

        $this->assertResponseIsSuccessful();
        //Fixture "Profile 1" (id 1) is a part profile shown in the dropdown
        $this->assertSelectorExists('.dropdown-menu '.self::PRINT_BUTTON
            .'[data-pages--print-agent-print-profile-value="1"]'
            .'[data-pages--print-agent-print-ids-value="1"]'
            .'[data-pages--print-agent-print-type-value="part"]');
    }
}
