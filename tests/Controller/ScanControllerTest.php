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
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ScanControllerTest extends WebTestCase
{

    private ?KernelBrowser $client = null;

    public function setUp(): void
    {
        $this->client = static::createClient([], [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'test',
        ]);
        $this->client->disableReboot();
        $this->client->catchExceptions(false);
    }

    public function testRedirectOnInputParameter(): void
    {
        $this->client->request('GET', '/en/scan?input=0000001');
        $this->assertResponseRedirects('/en/part/1');
    }

    public function testScanQRCode(): void
    {
        $this->client->request('GET', '/scan/part/1');
        $this->assertResponseRedirects('/en/part/1');
    }

    public function testLookupReturnsFoundOnKnownPart(): void
    {
        $this->client->request('POST', '/en/scan/lookup', [
            'input' => '0000001',
            'mode' => '',
            'info_mode' => 'true',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($data['ok']);
        $this->assertTrue($data['found']);
        $this->assertSame('/en/part/1', $data['redirectUrl']);
        $this->assertTrue($data['infoMode']);
        $this->assertIsString($data['html']);
        $this->assertNotSame('', trim($data['html']));
    }

    public function testLookupReturnsNotFoundOnUnknownPart(): void
    {
        $this->client->request('POST', '/en/scan/lookup', [
            // Use a valid LCSC barcode
            'input' => '{pbn:PICK2407080035,on:WM2407080118,pc:C365735,pm:ES8316,qty:12,mc:,cc:1,pdi:120044290,hp:null,wc:ZH}',
            'mode' => '',
            'info_mode' => 'true',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode((string)$this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($data['ok']);
        $this->assertFalse($data['found']);
        $this->assertSame(null, $data['redirectUrl']);
        $this->assertTrue($data['infoMode']);
        $this->assertIsString($data['html']);
        $this->assertNotSame('', trim($data['html']));
    }

    public function testLookupReturnsFalseOnGarbageInput(): void
    {
        $this->client->request('POST', '/en/scan/lookup', [
            'input' => 'not-a-real-barcode',
            'mode' => '',
            'info_mode' => 'false',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data['ok']);
    }
}
