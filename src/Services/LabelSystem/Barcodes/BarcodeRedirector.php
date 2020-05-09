<?php
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

namespace App\Services\LabelSystem\Barcodes;


use App\Entity\Parts\PartLot;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BarcodeRedirector
{
    private $urlGenerator;
    private $em;


    public function __construct(UrlGeneratorInterface $urlGenerator, EntityManagerInterface $entityManager)
    {
        $this->urlGenerator = $urlGenerator;
        $this->em = $entityManager;
    }

    /**
     * Determines the URL to which the user should be redirected, when scanning a QR code
     * @param  string  $type The type of the element that was scanned (e.g. 'part', 'lot', etc.)
     * @param  int  $id The ID of the element that was scanned
     * @return string The URL to which should be redirected.
     * @throws EntityNotFoundException
     */
    public function getRedirectURL(string $type, int $id): string
    {
        switch ($type) {
            case 'part':
                return $this->urlGenerator->generate('app_part_show', ['id' => $id]);
            case 'lot':
                //Try to determine the part to the given lot
                $lot = $this->em->find(PartLot::class, $id);
                if ($lot === null) {
                    throw new EntityNotFoundException();
                }

                return $this->urlGenerator->generate('app_part_show', ['id' => $lot->getPart()->getID()]);

            case 'location':
                return $this->urlGenerator->generate('part_list_store_location', ['id' => $id]);

            default:
                throw new \InvalidArgumentException('Unknown $type: ' . $type);
        }
    }
}