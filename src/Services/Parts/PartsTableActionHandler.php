<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\Parts;

use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Repository\DBElementRepository;
use App\Repository\PartRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

final class PartsTableActionHandler
{
    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    /**
     * Converts the given array to an array of Parts.
     *
     * @param string $ids a comma separated list of Part IDs
     *
     * @return Part[]
     */
    public function idStringToArray(string $ids): array
    {
        $id_array = explode(',', $ids);

        /** @var PartRepository $repo */
        $repo = $this->entityManager->getRepository(Part::class);

        return $repo->getElementsFromIDArray($id_array);
    }

    /**
     * @param Part[] $selected_parts
     */
    public function handleAction(string $action, array $selected_parts, ?int $target_id): void
    {
        //Iterate over the parts and apply the action to it:
        foreach ($selected_parts as $part) {
            if (!$part instanceof Part) {
                throw new InvalidArgumentException('$selected_parts must be an array of Part elements!');
            }

            //We modify parts, so you have to have the permission to modify it
            $this->denyAccessUnlessGranted('edit', $part);

            switch ($action) {
                case 'favorite':
                    $this->denyAccessUnlessGranted('change_favorite', $part);
                    $part->setFavorite(true);
                    break;
                case 'unfavorite':
                    $this->denyAccessUnlessGranted('change_favorite', $part);
                    $part->setFavorite(false);
                    break;
                case 'set_needs_review':
                    $this->denyAccessUnlessGranted('edit', $part);
                    $part->setNeedsReview(true);
                    break;
                case 'unset_needs_review':
                    $this->denyAccessUnlessGranted('edit', $part);
                    $part->setNeedsReview(false);
                    break;
                case 'delete':
                    $this->denyAccessUnlessGranted('delete', $part);
                    $this->entityManager->remove($part);
                    break;
                case 'change_category':
                    $this->denyAccessUnlessGranted('@categories.read');
                    $part->setCategory($this->entityManager->find(Category::class, $target_id));
                    break;
                case 'change_footprint':
                    $this->denyAccessUnlessGranted('@footprints.read');
                    $part->setFootprint(null === $target_id ? null : $this->entityManager->find(Footprint::class, $target_id));
                    break;
                case 'change_manufacturer':
                    $this->denyAccessUnlessGranted('@manufacturers.read');
                    $part->setManufacturer(null === $target_id ? null : $this->entityManager->find(Manufacturer::class, $target_id));
                    break;
                case 'change_unit':
                    $this->denyAccessUnlessGranted('@measurement_units.read');
                    $part->setPartUnit(null === $target_id ? null : $this->entityManager->find(MeasurementUnit::class, $target_id));
                    break;

                default:
                    throw new InvalidArgumentException('The given action is unknown! ('.$action.')');
            }
        }
    }

    /**
     * Throws an exception unless the attributes are granted against the current authentication token and optionally
     * supplied subject.
     *
     * @throws AccessDeniedException
     */
    private function denyAccessUnlessGranted($attributes, $subject = null, string $message = 'Access Denied.'): void
    {
        if (!$this->security->isGranted($attributes, $subject)) {
            $exception = new AccessDeniedException($message);
            $exception->setAttributes($attributes);
            $exception->setSubject($subject);

            throw $exception;
        }
    }
}
