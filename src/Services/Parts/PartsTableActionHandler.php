<?php

declare(strict_types=1);

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

use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Repository\DBElementRepository;
use App\Repository\PartRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PartsTableActionHandler
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly Security $security, private readonly UrlGeneratorInterface $urlGenerator)
    {
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
     * @return RedirectResponse|null Returns a redirect response if the user should be redirected to another page, otherwise null
     */
    public function handleAction(string $action, array $selected_parts, ?int $target_id, ?string $redirect_url = null): ?RedirectResponse
    {
        if ($action === 'add_to_project') {
            return new RedirectResponse(
                $this->urlGenerator->generate('project_add_parts', [
                    'id' => $target_id,
                    'parts' => implode(',', array_map(static fn (Part $part) => $part->getID(), $selected_parts)),
                    '_redirect' => $redirect_url
                ])
            );
        }

        if ($action === 'generate_label' || $action === 'generate_label_lot') {
            //For parts we can just use the comma separated part IDs
            if ($action === 'generate_label') {
                $targets = implode(',', array_map(static fn (Part $part) => $part->getID(), $selected_parts));
            } else { //For lots we have to extract the part lots
                $targets = implode(',', array_map(static fn(Part $part): string => //We concat the lot IDs of every part with a comma (which are later concated with a comma too per part)
implode(',', array_map(static fn (PartLot $lot) => $lot->getID(), $part->getPartLots()->toArray())), $selected_parts));
            }

            return new RedirectResponse(
                $this->urlGenerator->generate($target_id !== 0 && $target_id !== null ? 'label_dialog_profile' : 'label_dialog', [
                    'profile' => $target_id,
                    'target_id' => $targets,
                    'generate' => '1',
                    'target_type' => $action === 'generate_label_lot' ? 'part_lot' : 'part',
                ])
            );
        }

        //When action starts with "export_" we have to redirect to the export controller
        $matches = [];
        if (preg_match('/^export_(json|yaml|xml|csv)$/', $action, $matches)) {
            $ids = implode(',', array_map(static fn (Part $part) => $part->getID(), $selected_parts));
            $level = match ($target_id) {
                2 => 'extended',
                3 => 'full',
                default => 'simple',
            };


            return new RedirectResponse(
                $this->urlGenerator->generate('parts_export', [
                    'format' => $matches[1],
                    'level' => $level,
                    'ids' => $ids,
                    '_redirect' => $redirect_url
                ])
            );
        }


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

        return null;
    }

    /**
     * Throws an exception unless the attributes are granted against the current authentication token and optionally
     * supplied subject.
     *
     * @throws AccessDeniedException
     */
    private function denyAccessUnlessGranted(mixed $attributes, mixed $subject = null, string $message = 'Access Denied.'): void
    {
        if (!$this->security->isGranted($attributes, $subject)) {
            $exception = new AccessDeniedException($message);
            $exception->setAttributes($attributes);
            $exception->setSubject($subject);

            throw $exception;
        }
    }
}
