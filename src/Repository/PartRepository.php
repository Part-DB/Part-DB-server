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

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends NamedDBElementRepository<Part>
 */
class PartRepository extends NamedDBElementRepository
{
    private TranslatorInterface $translator;

    public function __construct(
        EntityManagerInterface $em,
        TranslatorInterface $translator
    ) {
        parent::__construct($em, $em->getClassMetadata(Part::class));

        $this->translator = $translator;
    }

    /**
     * Gets the summed up instock of all parts (only parts without a measurement unit).
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getPartsInstockSum(): float
    {
        $qb = new QueryBuilder($this->getEntityManager());
        $qb->select('SUM(part_lot.amount)')
            ->from(PartLot::class, 'part_lot')
            ->leftJoin('part_lot.part', 'part')
            ->where('part.partUnit IS NULL');

        $query = $qb->getQuery();

        return (float) ($query->getSingleScalarResult() ?? 0.0);
    }

    /**
     * Gets the number of parts that has price information.
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getPartsCountWithPrice(): int
    {
        $qb = $this->createQueryBuilder('part');
        $qb->select('COUNT(DISTINCT part)')
            ->innerJoin('part.orderdetails', 'orderdetail')
            ->innerJoin('orderdetail.pricedetails', 'pricedetail')
            ->where('pricedetail.price > 0.0');

        $query = $qb->getQuery();

        return (int) ($query->getSingleScalarResult() ?? 0);
    }

    /**
     * @return Part[]
     */
    public function autocompleteSearch(string $query, int $max_limits = 50): array
    {
        $qb = $this->createQueryBuilder('part');
        $qb->select('part')
            ->leftJoin('part.category', 'category')
            ->leftJoin('part.footprint', 'footprint')

            ->where('ILIKE(part.name, :query) = TRUE')
            ->orWhere('ILIKE(part.description, :query) = TRUE')
            ->orWhere('ILIKE(category.name, :query) = TRUE')
            ->orWhere('ILIKE(footprint.name, :query) = TRUE')
            ;

        $qb->setParameter('query', '%'.$query.'%');

        $qb->setMaxResults($max_limits);
        $qb->orderBy('NATSORT(part.name)', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function autoCompleteIpn(Part $part, int $autocompletePartDigits): array
    {
        $category = $part->getCategory();
        $ipnSuggestions = ['commonPrefixes' => [], 'prefixesPartIncrement' => []];

        // Validate the category and ensure it's an instance of Category
        if ($category instanceof Category) {
            $currentPath = $category->getPartIpnPrefix();
            $directIpnPrefixEmpty = $category->getPartIpnPrefix() === '';
            $currentPath = $currentPath === '' ? 'n.a.' : $currentPath;

            $increment = $this->generateNextPossiblePartIncrement($currentPath, $part, $autocompletePartDigits);

            $ipnSuggestions['commonPrefixes'][] = [
                'title' => $currentPath . '-',
                'description' => $directIpnPrefixEmpty ? $this->translator->trans('part.edit.tab.advanced.ipn.prefix_empty.direct_category', ['%name%' => $category->getName()]) : $this->translator->trans('part.edit.tab.advanced.ipn.prefix.direct_category')
            ];

            $ipnSuggestions['prefixesPartIncrement'][] = [
                'title' => $currentPath . '-' . $increment,
                'description' => $directIpnPrefixEmpty ? $this->translator->trans('part.edit.tab.advanced.ipn.prefix_empty.direct_category', ['%name%' => $category->getName()]) : $this->translator->trans('part.edit.tab.advanced.ipn.prefix.direct_category.increment')
            ];

            // Process parent categories
            $parentCategory = $category->getParent();

            while ($parentCategory instanceof Category) {
                // Prepend the parent category's prefix to the current path
                $currentPath = $parentCategory->getPartIpnPrefix() . '-' . $currentPath;
                $currentPath = $parentCategory->getPartIpnPrefix() === '' ? 'n.a.-' . $currentPath : $currentPath;

                $ipnSuggestions['commonPrefixes'][] = [
                    'title' => $currentPath . '-',
                    'description' => $this->translator->trans('part.edit.tab.advanced.ipn.prefix.hierarchical.no_increment')
                ];

                $increment = $this->generateNextPossiblePartIncrement($currentPath, $part, $autocompletePartDigits);

                $ipnSuggestions['prefixesPartIncrement'][] = [
                    'title' => $currentPath . '-' . $increment,
                    'description' => $this->translator->trans('part.edit.tab.advanced.ipn.prefix.hierarchical.increment')
                ];

                // Move to the next parent category
                $parentCategory = $parentCategory->getParent();
            }
        } elseif ($part->getID() === null) {
            $ipnSuggestions['commonPrefixes'][] = [
                'title' => 'n.a.',
                'description' => $this->translator->trans('part.edit.tab.advanced.ipn.prefix.not_saved')
            ];
        }

        return $ipnSuggestions;
    }

    public function generateNextPossiblePartIncrement(string $currentPath, Part $currentPart, int $autocompletePartDigits): string
    {
        $qb = $this->createQueryBuilder('part');

        $expectedLength = strlen($currentPath) + 1 + $autocompletePartDigits; // Path + '-' + $autocompletePartDigits digits

        // Fetch all parts in the given category, sorted by their ID in ascending order
        $qb->select('part')
            ->where('part.ipn LIKE :ipnPattern')
            ->andWhere('LENGTH(part.ipn) = :expectedLength')
            ->setParameter('ipnPattern', $currentPath . '%')
            ->setParameter('expectedLength', $expectedLength)
            ->orderBy('part.id', 'ASC');

        $parts = $qb->getQuery()->getResult();

        // Collect all used increments in the category
        $usedIncrements = [];
        foreach ($parts as $part) {
            if ($part->getIpn() === null || $part->getIpn() === '') {
                continue;
            }

            if ($part->getId() === $currentPart->getId()) {
                // Extract and return the current part's increment directly
                $incrementPart = substr($part->getIpn(), -$autocompletePartDigits);
                if (is_numeric($incrementPart)) {
                    return str_pad((string) $incrementPart, $autocompletePartDigits, '0', STR_PAD_LEFT);
                }
            }

            // Extract last $autocompletePartDigits digits for possible available part increment
            $incrementPart = substr($part->getIpn(), -$autocompletePartDigits);
            if (is_numeric($incrementPart)) {
                $usedIncrements[] = (int) $incrementPart;
            }

        }

        // Generate the next free $autocompletePartDigits-digit increment
        $nextIncrement = 1; // Start at the beginning

        while (in_array($nextIncrement, $usedIncrements)) {
            $nextIncrement++;
        }

        return str_pad((string) $nextIncrement, $autocompletePartDigits, '0', STR_PAD_LEFT);
    }
}
