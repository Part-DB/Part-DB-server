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

    /**
     * Provides IPN (Internal Part Number) suggestions for a given part based on its category, description,
     * and configured autocomplete digit length.
     *
     * This function generates suggestions for common prefixes and incremented prefixes based on
     * the part's current category and its hierarchy. If the part is unsaved, a default "n.a." prefix is returned.
     *
     * @param Part $part The part for which autocomplete suggestions are generated.
     * @param string $description Base64-encoded description to assist in generating suggestions.
     * @param int $autocompletePartDigits The number of digits used in autocomplete increments.
     *
     * @return array An associative array containing the following keys:
     *               - 'commonPrefixes': List of common prefixes found for the part.
     *               - 'prefixesPartIncrement': Increments for the generated prefixes, including hierarchical prefixes.
     */
    public function autoCompleteIpn(Part $part, string $description, int $autocompletePartDigits): array
    {
        $category = $part->getCategory();
        $ipnSuggestions = ['commonPrefixes' => [], 'prefixesPartIncrement' => []];
        $description = base64_decode($description);

        if (strlen($description) > 150) {
            $description = substr($description, 0, 150);
        }

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

            $suggestionByDescription = $this->getIpnSuggestByDescription($description);

            if ($suggestionByDescription !== null && $suggestionByDescription !== $part->getIpn() && $part->getIpn() !== null && $part->getIpn() !== '') {
                $ipnSuggestions['prefixesPartIncrement'][] = [
                    'title' => $part->getIpn(),
                    'description' =>  $this->translator->trans('part.edit.tab.advanced.ipn.prefix.description.current-increment')
                ];
            }

            if ($suggestionByDescription !== null) {
                $ipnSuggestions['prefixesPartIncrement'][] = [
                    'title' => $suggestionByDescription,
                    'description' =>  $this->translator->trans('part.edit.tab.advanced.ipn.prefix.description.increment')
                ];
            }

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

    /**
     * Suggests the next IPN (Internal Part Number) based on the provided part description.
     *
     * Searches for parts with similar descriptions and retrieves their existing IPNs to calculate the next suggestion.
     * Returns null if the description is empty or no suggestion can be generated.
     *
     * @param string $description The part description to search for.
     *
     * @return string|null The suggested IPN, or null if no suggestion is possible.
     *
     * @throws NonUniqueResultException
     */
    public function getIpnSuggestByDescription(string $description): ?string
    {
        if ($description === '') {
            return null;
        }

        $qb = $this->createQueryBuilder('part');

        $qb->select('part')
            ->where('part.description LIKE :descriptionPattern')
            ->setParameter('descriptionPattern', $description.'%')
            ->orderBy('part.id', 'ASC');

        $partsBySameDescription = $qb->getQuery()->getResult();
        $givenIpnsWithSameDescription = [];

        foreach ($partsBySameDescription as $part) {
            if ($part->getIpn() === null || $part->getIpn() === '') {
                continue;
            }

            $givenIpnsWithSameDescription[] = $part->getIpn();
        }

        return $this->getNextIpnSuggestion($givenIpnsWithSameDescription);
    }

    /**
     * Generates the next possible increment for a part within a given category, while ensuring uniqueness.
     *
     * This method calculates the next available increment for a part's identifier (`ipn`) based on the current path
     * and the number of digits specified for the autocomplete feature. It ensures that the generated identifier
     * aligns with the expected length and does not conflict with already existing identifiers in the same category.
     *
     * @param string $currentPath The base path or prefix for the part's identifier.
     * @param Part $currentPart The part entity for which the increment is being generated.
     * @param int $autocompletePartDigits The number of digits reserved for the increment.
     *
     * @return string|null The next possible increment as a zero-padded string, or null if it cannot be generated.
     *
     * @throws NonUniqueResultException If the query returns non-unique results.
     * @throws NoResultException If the query fails to return a result.
     */
    private function generateNextPossiblePartIncrement(string $currentPath, Part $currentPart, int $autocompletePartDigits): ?string
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

    /**
     * Generates the next IPN suggestion based on the maximum numeric suffix found in the given IPNs.
     *
     * The new IPN is constructed using the base format of the first provided IPN,
     * incremented by the next free numeric suffix. If no base IPNs are found,
     * returns null.
     *
     * @param array $givenIpns List of IPNs to analyze.
     *
     * @return string|null The next suggested IPN, or null if no base IPNs can be derived.
     */
    private function getNextIpnSuggestion(array $givenIpns): ?string {
        $maxSuffix = 0;

        foreach ($givenIpns as $ipn) {
            // Check whether the IPN contains a suffix "_ <number>"
            if (preg_match('/_(\d+)$/', $ipn, $matches)) {
                $suffix = (int)$matches[1];
                if ($suffix > $maxSuffix) {
                    $maxSuffix = $suffix; // Höchste Nummer speichern
                }
            }
        }

        // Find the basic format (the IPN without suffix) from the first IPN
        $baseIpn = $givenIpns[0] ?? '';
        $baseIpn = preg_replace('/_\d+$/', '', $baseIpn); // Entferne vorhandene "_<Zahl>"

        if ($baseIpn === '') {
            return null;
        }

        // Generate next free possible IPN
        return $baseIpn . '_' . ($maxSuffix + 1);
    }

}
