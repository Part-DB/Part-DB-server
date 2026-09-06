<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-server).
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

namespace App\Tests\Controller;

use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Functional smoke tests for the value calculator tool and the "Generate component images"
 * bulk action. They exercise the controller entry points (access control, classification loop,
 * EDA suggestions, rendering) end to end. Part edits are rolled back by DAMADoctrineTestBundle.
 */
#[Group('DB')]
#[Group('slow')]
final class ValueCalculatorControllerTest extends WebTestCase
{
    private function loginAdmin(): KernelBrowser
    {
        return $this->loginAs('admin');
    }

    private function loginAs(string $username): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['name' => $username]);
        if ($user === null) {
            $this->markTestSkipped("Fixture user '$username' not found.");
        }
        $client->loginUser($user);
        $client->followRedirects(false);

        return $client;
    }

    /**
     * Part-DB answers an unauthorized request with 401/403 or a redirect (to the login/permission
     * page) depending on context, so accept any of those — the point is that access is refused.
     */
    private function assertDenied(KernelBrowser $client): void
    {
        $code = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            $code === 401 || $code === 403 || $client->getResponse()->isRedirect(),
            "Expected 401/403/redirect for an unauthorized request, got $code"
        );
    }

    public function testValueCalculatorPageLoads(): void
    {
        $client = $this->loginAdmin();
        $client->request('GET', '/en/tools/component_image_generator');
        self::assertResponseIsSuccessful();
    }

    public function testValueCalculatorPrefillsFromPart(): void
    {
        $client = $this->loginAdmin();
        $client->request('GET', '/en/tools/component_image_generator?part=1');
        self::assertResponseIsSuccessful();
    }

    public function testBulkGenerateWithoutSelection(): void
    {
        $client = $this->loginAdmin();
        $client->request('GET', '/en/tools/bulk_generate_images');
        self::assertResponseIsSuccessful();
    }

    public function testBulkGenerateClassifiesAResistor(): void
    {
        $client = $this->loginAdmin();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $part = $em->find(Part::class, 1);
        if ($part === null) {
            $this->markTestSkipped('Fixture part #1 not found.');
        }
        //Make the part look like a resistor without a picture so it becomes a candidate and the
        //classification + EDA-suggestion code path runs.
        $part->setName('Resistor 10kΩ 0.25W 1% blue body 50ppm');
        $part->setMasterPictureAttachment(null);
        $em->flush();

        $client->request('GET', '/en/tools/bulk_generate_images?ids=1');
        self::assertResponseIsSuccessful();
    }

    public function testBulkGenerateOverwriteMode(): void
    {
        $client = $this->loginAdmin();
        $client->request('GET', '/en/tools/bulk_generate_images?ids=1&overwrite=1');
        self::assertResponseIsSuccessful();
    }

    public function testGenerateImageAttachesPicture(): void
    {
        $client = $this->loginAdmin();
        $row = $this->resistorCandidateRow($client);
        //The row carries the per-part generate endpoint and its CSRF token (as the JS uses them).
        $client->request('POST', (string) $row->attr('data-endpoint'), [
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="#000"/></svg>',
            'name' => 'Test generated image',
            'preview' => '1',
            '_token' => (string) $row->attr('data-csrf'),
        ], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertTrue($payload['success'] ?? false, 'Expected {success: true} from the generate-image endpoint.');

        //The picture must actually be attached and (preview=1) set as the master picture.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $part = $em->find(Part::class, 1);
        self::assertNotNull($part->getMasterPictureAttachment(), 'Generated image should be set as the master picture.');
    }

    public function testGenerateImageRejectsInvalidCsrf(): void
    {
        $client = $this->loginAdmin();
        $row = $this->resistorCandidateRow($client);
        $client->request('POST', (string) $row->attr('data-endpoint'), [
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>',
            'name' => 'Should be rejected',
            '_token' => 'definitely-not-a-valid-token',
        ], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        $this->assertDenied($client);
    }

    public function testValueCalculatorDeniedWithoutPermission(): void
    {
        $client = $this->loginAs('noread');
        $client->request('GET', '/en/tools/component_image_generator');
        $this->assertDenied($client);
    }

    public function testBulkGenerateDeniedWithoutPermission(): void
    {
        $client = $this->loginAs('noread');
        $client->request('GET', '/en/tools/bulk_generate_images?ids=1');
        $this->assertDenied($client);
    }

    public function testGenerateImageDeniedWithoutEditPermission(): void
    {
        $client = $this->loginAs('noread');
        $client->request('POST', '/en/part/1/generate_image', [
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>',
            '_token' => 'irrelevant-edit-is-checked-first',
        ], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $this->assertDenied($client);
    }

    public function testSetEdaWritesKicadFields(): void
    {
        $client = $this->loginAdmin();
        $row = $this->resistorCandidateRow($client);
        $client->request('POST', (string) $row->attr('data-eda-endpoint'), [
            'kicad_symbol' => 'Device:R',
            'reference_prefix' => 'R',
            'kicad_footprint' => 'Resistor_THT:R_Axial_DIN0207_L6.3mm_D2.5mm_P7.62mm_Horizontal',
            '_token' => (string) $row->attr('data-eda-csrf'),
        ], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertTrue($payload['success'] ?? false, 'Expected {success: true} from the set-eda endpoint.');

        //The EDA fields must actually be written to the part.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $eda = $em->find(Part::class, 1)->getEdaInfo();
        self::assertSame('Device:R', $eda->getKicadSymbol());
        self::assertSame('R', $eda->getReferencePrefix());
        self::assertSame('Resistor_THT:R_Axial_DIN0207_L6.3mm_D2.5mm_P7.62mm_Horizontal', $eda->getKicadFootprint());
    }

    /**
     * Renames fixture part #1 into a pictureless resistor and returns its candidate row from the
     * bulk review page — which carries the generate/set-eda endpoints and their CSRF tokens.
     */
    private function resistorCandidateRow(KernelBrowser $client): Crawler
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $part = $em->find(Part::class, 1);
        if ($part === null) {
            $this->markTestSkipped('Fixture part #1 not found.');
        }
        $part->setName('Resistor 10kΩ 0.25W 1% blue body');
        $part->setMasterPictureAttachment(null);
        $em->flush();

        $crawler = $client->request('GET', '/en/tools/bulk_generate_images?ids=1');
        self::assertResponseIsSuccessful();
        $row = $crawler->filter('tr[data-csrf]')->first();
        self::assertGreaterThan(0, $row->count(), 'Expected a classified candidate row on the bulk review page.');

        return $row;
    }
}
