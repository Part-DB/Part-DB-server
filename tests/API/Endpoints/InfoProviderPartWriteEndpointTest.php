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

namespace App\Tests\API\Endpoints;

use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Tests\API\AuthenticatedApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests the endpoints which create and update parts from an info provider, using the built-in TestProvider
 * as the provider (see App\Services\InfoProviderSystem\Providers\TestProvider, only registered in the test env).
 */
final class InfoProviderPartWriteEndpointTest extends AuthenticatedApiTestCase
{
    private const CREATE_URL = '/api/info_providers/create_part';
    private const UPDATE_URL = '/api/info_providers/update_part';

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function partCount(): int
    {
        return (int) $this->entityManager()->createQuery('SELECT COUNT(p.id) FROM '.Part::class.' p')
            ->getSingleScalarResult();
    }

    private function anyCategoryId(): int
    {
        return (int) $this->entityManager()->createQuery('SELECT MIN(c.id) FROM '.Category::class.' c')
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $content): array
    {
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testCreatePart(): void
    {
        $response = static::createAuthenticatedClient()->request('POST', self::CREATE_URL, ['json' => [
            'provider_key' => 'test',
            'provider_id' => 'element1',
            'category_id' => $this->anyCategoryId(),
        ]]);

        //200, not 201: the response describes an outcome, and a dry run creates nothing at all
        self::assertResponseStatusCodeSame(200);

        $data = $this->decode($response->getContent());
        self::assertSame('created', $data['status']);
        self::assertFalse($data['dry_run']);
        self::assertNotNull($data['part_id']);

        //The provider data must have been mapped by Part-DB itself, not by the caller
        $part = $this->entityManager()->find(Part::class, $data['part_id']);
        self::assertNotNull($part);
        self::assertSame('Test Element', $part->getName());
        self::assertSame('1234', $part->getManufacturerProductNumber());
        //The new part stays linked to the provider it came from, so it can be updated later without arguments
        self::assertSame('test', $part->getProviderReference()->getProviderKey());
        self::assertSame('element1', $part->getProviderReference()->getProviderId());
    }

    public function testCreatePartDryRunWritesNothing(): void
    {
        $before = $this->partCount();

        $response = static::createAuthenticatedClient()->request('POST', self::CREATE_URL, ['json' => [
            'provider_key' => 'test',
            'provider_id' => 'element1',
            'category_id' => $this->anyCategoryId(),
            'dry_run' => true,
        ]]);

        self::assertResponseIsSuccessful();

        $data = $this->decode($response->getContent());
        self::assertSame('created', $data['status']);
        self::assertTrue($data['dry_run']);
        //Nothing was written, so there is no part to point at
        self::assertNull($data['part_id']);
        //The fields which would have been filled are still reported, so a run can be reviewed beforehand
        self::assertNotEmpty($data['changes']);

        self::assertSame($before, $this->partCount(), 'A dry run must not create a part');
    }

    public function testUpdateOfAFreshlyCreatedPartReportsNoChanges(): void
    {
        $client = static::createAuthenticatedClient();

        $created = $this->decode($client->request('POST', self::CREATE_URL, ['json' => [
            'provider_key' => 'test',
            'provider_id' => 'element1',
            'category_id' => $this->anyCategoryId(),
        ]])->getContent());

        //Updating right away must be a no-op: the stored part already holds exactly this provider's data
        $response = $client->request('POST', self::UPDATE_URL, ['json' => [
            'part_id' => $created['part_id'],
        ]]);

        self::assertResponseStatusCodeSame(200);

        $data = $this->decode($response->getContent());
        self::assertSame('unchanged', $data['status']);
        self::assertSame([], $data['changes']);
    }

    public function testUpdateRefillsAnEmptiedFieldAndReportsIt(): void
    {
        $client = static::createAuthenticatedClient();

        $created = $this->decode($client->request('POST', self::CREATE_URL, ['json' => [
            'provider_key' => 'test',
            'provider_id' => 'element1',
            'category_id' => $this->anyCategoryId(),
        ]])->getContent());

        //Clear a field the provider fills, so the merger has something to do on the next update
        $em = $this->entityManager();
        $part = $em->find(Part::class, $created['part_id']);
        $part->setManufacturerProductNumber('');
        $em->flush();
        $em->clear();

        $response = $client->request('POST', self::UPDATE_URL, ['json' => [
            'part_id' => $created['part_id'],
        ]]);

        self::assertResponseIsSuccessful();

        $data = $this->decode($response->getContent());
        self::assertSame('updated', $data['status']);

        $changed_fields = array_column($data['changes'], 'field');
        self::assertContains('manufacturer_product_number', $changed_fields);

        $index = array_search('manufacturer_product_number', $changed_fields, true);
        self::assertSame('', $data['changes'][$index]['old']);
        self::assertSame('1234', $data['changes'][$index]['new']);

        //And the value must actually be stored, not just reported
        self::assertSame('1234', $this->entityManager()->find(Part::class, $created['part_id'])
            ->getManufacturerProductNumber());
    }

    public function testUpdateDryRunWritesNothing(): void
    {
        $client = static::createAuthenticatedClient();

        $created = $this->decode($client->request('POST', self::CREATE_URL, ['json' => [
            'provider_key' => 'test',
            'provider_id' => 'element1',
            'category_id' => $this->anyCategoryId(),
        ]])->getContent());

        $em = $this->entityManager();
        $part = $em->find(Part::class, $created['part_id']);
        $part->setManufacturerProductNumber('');
        $em->flush();
        $em->clear();

        $response = $client->request('POST', self::UPDATE_URL, ['json' => [
            'part_id' => $created['part_id'],
            'dry_run' => true,
        ]]);

        self::assertResponseIsSuccessful();

        $data = $this->decode($response->getContent());
        self::assertSame('updated', $data['status']);
        self::assertTrue($data['dry_run']);
        self::assertNotEmpty($data['changes']);

        //The stored part must still be untouched
        self::assertSame('', $this->entityManager()->find(Part::class, $created['part_id'])
            ->getManufacturerProductNumber());
    }

    public function testUpdateOfAPartWithoutProviderIsRejected(): void
    {
        //Part 1 comes from the fixtures and was never created from an info provider
        static::createAuthenticatedClient()->request('POST', self::UPDATE_URL, ['json' => ['part_id' => 1]]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testUpdateOfAnUnknownPartIsRejected(): void
    {
        static::createAuthenticatedClient()->request('POST', self::UPDATE_URL, ['json' => ['part_id' => 999999]]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownProviderIsRejected(): void
    {
        static::createAuthenticatedClient()->request('POST', self::CREATE_URL, ['json' => [
            'provider_key' => 'this_provider_does_not_exist',
            'provider_id' => 'element1',
        ]]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testEndpointsRequireAuthentication(): void
    {
        static::createClient()->request('POST', self::CREATE_URL, ['json' => [
            'provider_key' => 'test',
            'provider_id' => 'element1',
            'category_id' => $this->anyCategoryId(),
        ]]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testAFieldWhichWasEmptyBeforeIsReportedWithAnExplicitNull(): void
    {
        //A newly filled field has no previous value, and the response has to say so explicitly instead of
        //dropping the key - otherwise a caller cannot tell "was empty" apart from "not reported"
        $response = static::createAuthenticatedClient()->request('POST', self::CREATE_URL, ['json' => [
            'provider_key' => 'test',
            'provider_id' => 'element1',
            'category_id' => $this->anyCategoryId(),
            'dry_run' => true,
        ]]);

        self::assertResponseIsSuccessful();

        $data = $this->decode($response->getContent());
        $fields = array_column($data['changes'], 'field');
        self::assertContains('name', $fields);

        $change = $data['changes'][array_search('name', $fields, true)];
        self::assertArrayHasKey('old', $change, 'A null value must not be dropped from the response');
        self::assertNull($change['old']);
        self::assertSame('Test Element', $change['new']);
    }

    public function testAnUpdateActuallyPersistsNewlyMergedAttachments(): void
    {
        //Regression test: asking Doctrine to compute the changesets before the flush cascade-persisted the
        //merged attachments and spoiled their insert changesets, so they were reported as added but never
        //written. The change list is therefore derived from the original entity data instead.
        $client = static::createAuthenticatedClient();

        $created = $this->decode($client->request('POST', self::CREATE_URL, ['json' => [
            'provider_key' => 'test',
            'provider_id' => 'element1',
            'category_id' => $this->anyCategoryId(),
        ]])->getContent());

        $em = $this->entityManager();
        $part = $em->find(Part::class, $created['part_id']);
        $expected = $part->getAttachments()->count();
        self::assertGreaterThan(0, $expected, 'The test provider is expected to supply attachments');

        foreach ($part->getAttachments()->toArray() as $attachment) {
            $part->removeAttachment($attachment);
            $em->remove($attachment);
        }
        $em->flush();
        $em->clear();

        $response = $client->request('POST', self::UPDATE_URL, ['json' => ['part_id' => $created['part_id']]]);

        self::assertResponseIsSuccessful();

        $data = $this->decode($response->getContent());
        self::assertSame('updated', $data['status']);
        self::assertContains('attachments', array_column($data['changes'], 'field'));

        //The attachments must really be in the database, not only in the report
        $em = $this->entityManager();
        $em->clear();
        self::assertCount($expected, $em->find(Part::class, $created['part_id'])->getAttachments());
    }
}
