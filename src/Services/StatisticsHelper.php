<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Services;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Devices\Device;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class StatisticsHelper
{
    protected $em;
    protected $part_repo;
    protected $attachment_repo;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->part_repo = $this->em->getRepository(Part::class);
        $this->attachment_repo = $this->em->getRepository(Attachment::class);
    }

    /**
     *  Returns the count of distinct parts.
     */
    public function getDistinctPartsCount(): int
    {
        return $this->part_repo->count([]);
    }

    /**
     * Returns the summed instocked over all parts (only parts without a measurement unit).
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getPartsInstockSum(): float
    {
        return $this->part_repo->getPartsInstockSum();
    }

    /**
     * Returns the number of all parts which have price informations.
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getPartsCountWithPrice(): int
    {
        return $this->part_repo->getPartsCountWithPrice();
    }

    /**
     * Returns the number of datastructures for the given type.
     */
    public function getDataStructuresCount(string $type): int
    {
        $arr = [
            'attachment_type' => AttachmentType::class,
            'category' => Category::class,
            'device' => Device::class,
            'footprint' => Footprint::class,
            'manufacturer' => Manufacturer::class,
            'measurement_unit' => MeasurementUnit::class,
            'storelocation' => Storelocation::class,
            'supplier' => Supplier::class,
            'currency' => Currency::class,
        ];

        if (!isset($arr[$type])) {
            throw new \InvalidArgumentException('No count for the given type available!');
        }

        /** @var EntityRepository $repo */
        $repo = $this->em->getRepository($arr[$type]);

        return $repo->count([]);
    }

    /**
     * Gets the count of all attachments.
     */
    public function getAttachmentsCount(): int
    {
        return $this->attachment_repo->count([]);
    }

    /**
     * Gets the count of all private/secure attachments.
     */
    public function getPrivateAttachmentsCount(): int
    {
        return $this->attachment_repo->getPrivateAttachmentsCount();
    }

    /**
     * Gets the count of all external (only containing an URL) attachments.
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getExternalAttachmentsCount(): int
    {
        return $this->attachment_repo->getExternalAttachments();
    }

    /**
     * Gets the count of all attachments where the user uploaded an file.
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getUserUploadedAttachmentsCount(): int
    {
        return $this->attachment_repo->getUserUploadedAttachments();
    }
}
