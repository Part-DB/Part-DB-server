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
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Generic list/search processor shared by all structural "master data" entities (categories, footprints,
 * manufacturers, storage locations, suppliers, measurement units, part custom states). The concrete entity
 * class is determined from the operation, so this single processor can be reused for all of them.
 * Returns a lean id+name+full_path overview only; use the corresponding get_X_details tool for full details.
 */
readonly class ListStructuralElementsProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NodesListBuilder $nodesListBuilder,
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

        if ($data->keyword === null || $data->keyword === '') {
            //Without a filter, use the (cached) NodesListBuilder, which already returns the elements in
            //correct hierarchical tree order (parents immediately followed by their own children), instead
            //of fetching everything and re-sorting it ourselves.
            /** @var AbstractStructuralDBElement[] $elements */
            $elements = $this->nodesListBuilder->typeToNodesList($class);

            return array_map($this->toOverview(...), $elements);
        }

        $qb = $this->entityManager->getRepository($class)->createQueryBuilder('element');

        //Escape % and _ characters in the keyword, like PartSearchFilter does
        $keyword = str_replace(['%', '_'], ['\%', '\_'], $data->keyword);
        $qb->andWhere('ILIKE(element.name, :keyword) = TRUE OR ILIKE(element.comment, :keyword) = TRUE')
            ->setParameter('keyword', '%'.$keyword.'%');

        /** @var AbstractStructuralDBElement[] $elements */
        $elements = $qb->getQuery()->getResult();

        $overviews = array_map($this->toOverview(...), $elements);

        //Sort by full path (rather than the DB query), so that parents are always immediately followed by
        //their own children, allowing clients to derive the hierarchical structure from a flat list.
        usort($overviews, static fn (StructuralElementOverview $a, StructuralElementOverview $b): int => strnatcasecmp($a->full_path, $b->full_path));

        return $overviews;
    }

    private function toOverview(AbstractStructuralDBElement $element): StructuralElementOverview
    {
        return new StructuralElementOverview(
            id: $element->getID(),
            name: $element->getName(),
            full_path: $element->getFullPath(),
        );
    }
}
