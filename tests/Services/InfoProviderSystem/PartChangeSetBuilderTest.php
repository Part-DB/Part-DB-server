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

namespace App\Tests\Services\InfoProviderSystem;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\ManufacturingStatus;
use App\Entity\Parts\Part;
use App\Services\EntityMergers\Mergers\PartMerger;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\PartChangeSetBuilder;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @see PartChangeSetBuilder
 */
final class PartChangeSetBuilderTest extends KernelTestCase
{
    private PartChangeSetBuilder $service;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(PartChangeSetBuilder::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    private function part(): Part
    {
        return $this->entityManager->find(Part::class, 1);
    }

    /**
     * @param  \App\ApiResource\InfoProviderSystem\PartFieldChange[]  $changes
     * @return array<string, array{string|null, string|null}>
     */
    private function asMap(array $changes): array
    {
        $map = [];
        foreach ($changes as $change) {
            $map[$change->field] = [$change->old, $change->new];
        }

        return $map;
    }

    public function testAnUntouchedPartHasNoChanges(): void
    {
        self::assertSame([], $this->service->build($this->part()));
    }

    public function testAnUnmanagedPartHasNoChanges(): void
    {
        //A part which was never persisted has no stored state to compare against
        self::assertSame([], $this->service->build(new Part()));
    }

    public function testScalarChangesAreReportedWithOldAndNewValue(): void
    {
        $part = $this->part();
        $old_name = $part->getName();

        $part->setName('a new name');
        $part->setComment('a new comment');

        $changes = $this->asMap($this->service->build($part));

        self::assertArrayHasKey('name', $changes);
        self::assertSame([$old_name, 'a new name'], $changes['name']);
        self::assertArrayHasKey('comment', $changes);
        self::assertSame('a new comment', $changes['comment'][1]);
    }

    public function testChangesAreOrderedByFieldName(): void
    {
        $part = $this->part();
        $part->setName('zzz');
        $part->setComment('aaa');

        $fields = array_map(static fn ($change) => $change->field, $this->service->build($part));
        $sorted = $fields;
        sort($sorted);

        self::assertSame($sorted, $fields);
    }

    public function testEnumValuesAreReportedByTheirScalarValue(): void
    {
        $part = $this->part();
        $part->setManufacturingStatus(ManufacturingStatus::EOL);

        $changes = $this->asMap($this->service->build($part));

        self::assertArrayHasKey('manufacturing_status', $changes);
        self::assertSame(ManufacturingStatus::EOL->value, $changes['manufacturing_status'][1]);
    }

    public function testBooleanValuesAreReportedAsTrueOrFalse(): void
    {
        $part = $this->part();
        $part->setFavorite(!$part->isFavorite());

        $changes = $this->asMap($this->service->build($part));

        self::assertArrayHasKey('favorite', $changes);
        self::assertContains($changes['favorite'][1], ['true', 'false']);
        self::assertNotSame($changes['favorite'][0], $changes['favorite'][1]);
    }

    public function testAddedCollectionItemsAreReportedAsASizeChange(): void
    {
        $part = $this->part();
        $before = $part->getAttachments()->count();

        $part->addAttachment(new PartAttachment());

        $changes = $this->asMap($this->service->build($part));

        self::assertArrayHasKey('attachments', $changes);
        self::assertSame([(string) $before, (string) ($before + 1)], $changes['attachments']);
    }

    public function testBookkeepingFieldsAreNotReported(): void
    {
        $part = $this->part();
        $part->setName('a new name');
        $part->updateTimestamps();

        $fields = array_map(static fn ($change) => $change->field, $this->service->build($part));

        //lastModified changes on every single write and would only add noise to the response
        self::assertNotContains('lastModified', $fields);
    }

    public function testAChangedItemInsideACollectionIsReported(): void
    {
        //An info provider update can change an existing attachment (a refreshed external URL, for example)
        //without adding or removing one - the collection then has to be reported as changed even though its
        //size stays the same
        $part = $this->part();

        $attachment = (new PartAttachment())->setName('a datasheet')
            ->setAttachmentType($this->entityManager->find(AttachmentType::class, 1));
        $part->addAttachment($attachment);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $part = $this->part();
        $stored = $part->getAttachments()->last();
        $stored->setName('a renamed datasheet');

        $changes = $this->asMap($this->service->build($part));

        self::assertArrayHasKey('attachments', $changes, 'A modified item must mark its collection as changed');
    }

    public function testBuildingTheChangeListDoesNotBreakTheFollowingFlush(): void
    {
        //Regression test for the nested case: asking Doctrine's UnitOfWork to compute the changesets before the
        //flush cascade-persisted the merged orderdetails and their pricedetails, and spoiled their insert
        //changesets when the flush computed them a second time - the nested pricedetails were then written
        //incompletely or not at all. Building the change list must therefore leave the flush untouched.
        $part = $this->part();

        $dto = new PartDetailDTO(
            provider_key: 'test',
            provider_id: 'nested-1',
            name: $part->getName(),
            description: '',
            vendor_infos: [
                new PurchaseInfoDTO(
                    distributor_name: 'A distributor for this test',
                    order_number: 'ORDER-4711',
                    prices: [
                        new PriceDTO(minimum_discount_amount: 1.0, price: '1.50', currency_iso_code: 'EUR'),
                        new PriceDTO(minimum_discount_amount: 10.0, price: '1.20', currency_iso_code: 'EUR'),
                    ],
                ),
            ],
        );

        $provider_part = self::getContainer()->get(PartInfoRetriever::class)->dtoToPart($dto);
        self::getContainer()->get(PartMerger::class)->merge($part, $provider_part);

        //This is what the writer does before it flushes
        $changes = $this->asMap($this->service->build($part));
        self::assertArrayHasKey('orderdetails', $changes);

        //The new structural entities are persisted by the writer, mirrored here
        foreach ($part->getOrderdetails() as $orderdetail) {
            $supplier = $orderdetail->getSupplier();
            if ($supplier !== null && $supplier->getID() === null) {
                $this->entityManager->persist($supplier);
            }
            foreach ($orderdetail->getPricedetails() as $pricedetail) {
                $currency = $pricedetail->getCurrency();
                if ($currency !== null && $currency->getID() === null) {
                    $this->entityManager->persist($currency);
                }
            }
        }

        $this->entityManager->flush();
        $id = $part->getID();
        $this->entityManager->clear();

        //Everything the change list announced must really be in the database, nested prices included
        $stored = $this->entityManager->find(Part::class, $id);
        $orderdetail = null;
        foreach ($stored->getOrderdetails() as $candidate) {
            if ($candidate->getSupplierPartNr() === 'ORDER-4711') {
                $orderdetail = $candidate;
            }
        }

        self::assertNotNull($orderdetail, 'The merged orderdetail must have been written');
        self::assertCount(2, $orderdetail->getPricedetails(), 'Both nested pricedetails must have been written');
    }
}
