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

namespace App\State\Mcp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Mcp\DTO\StructuralElementOverview;
use App\Mcp\DTO\StructuralElementSearchInput;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Generic list/search processor shared by all structural "master data" entities (categories, footprints,
 * manufacturers, storage locations, suppliers, measurement units, part custom states). The concrete entity
 * class is determined from the operation, so this single processor can be reused for all of them.
 * Returns a lean id+name overview only; use the corresponding get_X_details tool for full details.
 */
class ListStructuralElementsProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return StructuralElementOverview[]
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        if (!$data instanceof StructuralElementSearchInput) {
            throw new \InvalidArgumentException('Expected StructuralElementSearchInput');
        }

        $class = $operation->getClass();
        if (!is_a($class, AbstractStructuralDBElement::class, true)) {
            throw new \LogicException(sprintf('%s can only be used for resources extending %s', self::class, AbstractStructuralDBElement::class));
        }

        $qb = $this->entityManager->getRepository($class)->createQueryBuilder('element');
        $qb->select('element.id AS id', 'element.name AS name');

        if ($data->keyword !== null && $data->keyword !== '') {
            //Escape % and _ characters in the keyword, like PartSearchFilter does
            $keyword = str_replace(['%', '_'], ['\%', '\_'], $data->keyword);
            $qb->andWhere('ILIKE(element.name, :keyword) = TRUE OR ILIKE(element.comment, :keyword) = TRUE')
                ->setParameter('keyword', '%'.$keyword.'%');
        }

        $qb->orderBy('element.name', 'ASC');

        $rows = $qb->getQuery()->getArrayResult();

        return array_map(
            static fn (array $row): StructuralElementOverview => new StructuralElementOverview(id: $row['id'], name: $row['name']),
            $rows
        );
    }
}
