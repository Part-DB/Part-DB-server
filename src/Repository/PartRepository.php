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

use App\DataTables\Filters\PartSearchFilter;
use App\DataTables\Filters\PartSearchSort;
use App\Doctrine\Helpers\FieldHelper;
use App\Entity\Attachments\Attachment;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\PriceInformations\Orderdetail;
use App\Services\Trees\StructuralElementPathCache;
use App\Settings\MiscSettings\IpnSuggestSettings;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends NamedDBElementRepository<Part>
 *
 * The data of a part needed to show it in the autocomplete search results, without loading the part entity.
 * The preview attachments are the candidates for the preview picture (master picture of the part, its footprint and
 * the project it is a build part of) in the order of their priority, see PartPreviewGenerator::getFirstValidPicture().
 * @phpstan-type AutocompletePartData array{id: int, name: string, description: string, category_id: int|null,
 *     category: string|null, footprint: string|null, manufacturer: string|null, addedDate: \DateTimeImmutable|null,
 *     lastModified: \DateTimeImmutable|null, suppliers: list<string>, preview_attachments: list<Attachment|null>}
 */
#[Autoconfigure(tags: ['doctrine.repository_service'])]
class PartRepository extends NamedDBElementRepository
{
    private TranslatorInterface $translator;
    private IpnSuggestSettings $ipnSuggestSettings;

    public function __construct(
        EntityManagerInterface $em,
        TranslatorInterface    $translator,
        IpnSuggestSettings     $ipnSuggestSettings,
        private readonly StructuralElementPathCache $pathCache,
    ) {
        parent::__construct($em, $em->getClassMetadata(Part::class));

        $this->translator = $translator;
        $this->ipnSuggestSettings = $ipnSuggestSettings;
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
     * Simple and fast search for the part select fields: matches the name, description, category and footprint.
     * @param  PartSearchSort|null  $sort  The ordering of the results, or null to order them by their name
     * @param  \SortDirection  $direction  The direction of the ordering given by $sort
     * @return list<AutocompletePartData>
     */
    public function autocompleteSearch(string $query, int $max_limits = 50, ?PartSearchSort $sort = null, \SortDirection $direction = \SortDirection::Ascending): array
    {
        $qb = $this->createQueryBuilder('part');
        $qb->select('part.id')
            ->leftJoin('part.category', '_category')
            ->leftJoin('part.footprint', '_footprint')
            ->where('ILIKE(part.name, :query) = TRUE')
            ->orWhere('ILIKE(part.description, :query) = TRUE')
            ->orWhere('ILIKE(_category.name, :query) = TRUE')
            ->orWhere('ILIKE(_footprint.name, :query) = TRUE')
            ->groupBy('part.id')
            ->setParameter('query', '%'.$query.'%');

        return $this->getSortedAutocompleteResults($qb, $sort, $direction, $max_limits);
    }

    /**
     * Search parts the same way as the search page does (selected fields, regex, extensive and wildcard matching),
     * but limited to a small number of results, so it can be used for autocompletion.
     * @param  PartSearchSort|null  $sort  The ordering of the results, or null to order them by their name
     * @param  \SortDirection  $direction  The direction of the ordering given by $sort
     * @return list<AutocompletePartData>
     */
    public function autocompleteSearchWithFilter(PartSearchFilter $filter, int $max_limits = 50, ?PartSearchSort $sort = null, \SortDirection $direction = \SortDirection::Ascending): array
    {
        //Select only the IDs first, as the joins needed by the filter would otherwise multiply the rows
        $qb = $this->createQueryBuilder('part');
        $qb->select('part.id')
            ->leftJoin('part.category', '_category')
            ->leftJoin('part.footprint', '_footprint')
            ->leftJoin('part.manufacturer', '_manufacturer')
            ->leftJoin('part.partLots', '_partLots')
            ->leftJoin('_partLots.storage_location', '_storelocations')
            ->leftJoin('part.orderdetails', '_orderdetails')
            ->leftJoin('_orderdetails.supplier', '_suppliers')
            ->groupBy('part.id');

        $filter->apply($qb);

        return $this->getSortedAutocompleteResults($qb, $sort, $direction, $max_limits);
    }

    /**
     * Returns the first parts found by the given query in the given ordering. Parts which have no value to sort by
     * (e.g. no manufacturer) are always put at the end, parts with the same value are ordered by their name.
     * @param  QueryBuilder  $qb  A query selecting the IDs of the found parts (alias "part") grouped by part.id. Joined
     * entities must use the alias of the parts table (underscore prefix).
     * @param  PartSearchSort|null  $sort  The ordering of the results, or null to order them by their name
     * @return list<AutocompletePartData>
     */
    private function getSortedAutocompleteResults(QueryBuilder $qb, ?PartSearchSort $sort, \SortDirection $direction, int $max_limits): array
    {
        $sort ??= PartSearchSort::NAME;

        if ($sort === PartSearchSort::TOP_CATEGORY) {
            $ids = $this->getIdsSortedByTopCategory($qb, $direction, $max_limits);
        } else {
            $aliases = $qb->getAllAliases();
            if ($sort === PartSearchSort::MANUFACTURER && !in_array('_manufacturer', $aliases, true)) {
                $qb->leftJoin('part.manufacturer', '_manufacturer');
            }
            if ($sort === PartSearchSort::SUPPLIER && !in_array('_suppliers', $aliases, true)) {
                $qb->leftJoin('part.orderdetails', '_orderdetails')
                    ->leftJoin('_orderdetails.supplier', '_suppliers');
            }

            //The joined values must be aggregated, as the query is grouped by the part. A part can have multiple
            //suppliers, the alphabetically first one is used then.
            [$value, $order] = match ($sort) {
                PartSearchSort::NAME => [null, 'NATSORT(part.name)'],
                PartSearchSort::MANUFACTURER => ['MIN(_manufacturer.name)', 'NATSORT(MIN(_manufacturer.name))'],
                PartSearchSort::SUPPLIER => ['MIN(_suppliers.name)', 'NATSORT(MIN(_suppliers.name))'],
                PartSearchSort::ADDED_DATE => ['part.addedDate', 'part.addedDate'],
                PartSearchSort::LAST_MODIFIED => ['part.lastModified', 'part.lastModified'],
                PartSearchSort::CATEGORY => ['MIN(_category.name)', 'NATSORT(MIN(_category.name))'],
            };

            //The databases disagree on where NULL values are sorted, so explicitly put them last in both directions
            if ($value !== null) {
                $qb->addSelect('CASE WHEN ' . $value . ' IS NULL THEN 1 ELSE 0 END AS HIDDEN sort_is_null')
                    ->addOrderBy('sort_is_null', 'ASC');
            }

            $qb->addOrderBy($order, $direction === \SortDirection::Descending ? 'DESC' : 'ASC')
                ->addOrderBy('NATSORT(part.name)', 'ASC')
                ->addOrderBy('part.id', 'ASC')
                ->setMaxResults($max_limits);

            $ids = array_column($qb->getQuery()->getArrayResult(), 'id');
        }

        return $this->getAutocompleteData($ids);
    }

    /**
     * Loads the data needed to show the given parts in the autocomplete search results. Only the needed fields are
     * selected instead of loading the part entities and their associations one by one.
     * @param  int[]  $ids
     * @return list<AutocompletePartData> The data of the parts in the same order as the given IDs
     */
    private function getAutocompleteData(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('part')
            ->select('part.id', 'part.name', 'part.description', 'IDENTITY(part.category) AS category_id',
                '_category.name AS category', '_footprint.name AS footprint', '_manufacturer.name AS manufacturer',
                'part.addedDate', 'part.lastModified',
                'IDENTITY(part.master_picture_attachment) AS part_picture_id',
                'IDENTITY(_footprint.master_picture_attachment) AS footprint_picture_id',
                'IDENTITY(_built_project.master_picture_attachment) AS project_picture_id')
            ->leftJoin('part.category', '_category')
            ->leftJoin('part.footprint', '_footprint')
            ->leftJoin('part.manufacturer', '_manufacturer')
            ->leftJoin('part.built_project', '_built_project')
            ->where('part.id IN (:ids)')
            ->setParameter('ids', $ids);

        //Get the rows in the same order as the sorted IDs
        FieldHelper::addOrderByFieldParam($qb, 'part.id', 'ids');
        $rows = $qb->getQuery()->getArrayResult();

        //Load all the preview picture candidates at once
        $picture_columns = ['part_picture_id', 'footprint_picture_id', 'project_picture_id'];
        $picture_ids = [];
        foreach ($rows as $row) {
            foreach ($picture_columns as $column) {
                if ($row[$column] !== null) {
                    $picture_ids[(int) $row[$column]] = (int) $row[$column];
                }
            }
        }
        $picture_ids = array_values($picture_ids);
        $attachments = [];
        if ($picture_ids !== []) {
            foreach ($this->getEntityManager()->getRepository(Attachment::class)->findBy(['id' => $picture_ids]) as $attachment) {
                $attachments[$attachment->getID()] = $attachment;
            }
        }

        //Load the names of the suppliers of all parts at once
        $suppliers = [];
        $supplier_rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(orderdetail.part) AS part_id', 'supplier.name')
            ->from(Orderdetail::class, 'orderdetail')
            ->innerJoin('orderdetail.supplier', 'supplier')
            ->where('orderdetail.part IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getArrayResult();
        foreach ($supplier_rows as $supplier_row) {
            $suppliers[(int) $supplier_row['part_id']][$supplier_row['name']] = $supplier_row['name'];
        }

        return array_map(static function (array $row) use ($attachments, $suppliers, $picture_columns): array {
            $id = (int) $row['id'];
            $part_suppliers = $suppliers[$id] ?? [];
            natcasesort($part_suppliers);

            return [
                'id' => $id,
                'name' => $row['name'],
                'description' => $row['description'],
                'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
                'category' => $row['category'],
                'footprint' => $row['footprint'],
                'manufacturer' => $row['manufacturer'],
                'addedDate' => $row['addedDate'],
                'lastModified' => $row['lastModified'],
                'suppliers' => array_values($part_suppliers),
                'preview_attachments' => array_map(
                    static fn(string $column) => $row[$column] !== null ? ($attachments[(int) $row[$column]] ?? null) : null,
                    $picture_columns
                ),
            ];
        }, $rows);
    }

    /**
     * Returns the IDs of the first parts found by the given query, ordered by the name of the root category their
     * category is contained in.
     * The root category is not stored in the database, so it is determined (and sorted by) in PHP using the cached
     * category paths, which only requires the ID and category ID of each found part.
     * @return int[]
     */
    private function getIdsSortedByTopCategory(QueryBuilder $qb, \SortDirection $direction, int $max_limits): array
    {
        $rows = $qb->addSelect('IDENTITY(part.category) AS category_id')
            ->addOrderBy('NATSORT(part.name)', 'ASC')
            ->addOrderBy('part.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $root_names = [];
        foreach ($rows as $row) {
            $category_id = $row['category_id'] !== null ? (int) $row['category_id'] : null;
            $root_names[(int) $row['id']] = $category_id !== null
                ? ($this->pathCache->getPathNames(Category::class, $category_id)[0] ?? null)
                : null;
        }

        //usort is stable, so parts of the same root category stay ordered by their name
        usort($rows, static function (array $a, array $b) use ($root_names, $direction): int {
            $name_a = $root_names[(int) $a['id']];
            $name_b = $root_names[(int) $b['id']];

            //Parts without a category are always put at the end
            if ($name_a === null || $name_b === null) {
                return ($name_a === null) <=> ($name_b === null);
            }

            return $direction === \SortDirection::Descending ? strnatcasecmp($name_b, $name_a) : strnatcasecmp($name_a, $name_b);
        });

        return array_map(intval(...), array_column(array_slice($rows, 0, $max_limits), 'id'));
    }

    /**
     * Provides IPN (Internal Part Number) suggestions for a given part based on its category, description,
     * and configured autocomplete digit length.
     *
     * This function generates suggestions for common prefixes and incremented prefixes based on
     * the part's current category and its hierarchy. If the part is unsaved, a default "n.a." prefix is returned.
     *
     * @param Part $part The part for which autocomplete suggestions are generated.
     * @param string $description description to assist in generating suggestions.
     * @param int $suggestPartDigits The number of digits used in autocomplete increments.
     *
     * @return array An associative array containing the following keys:
     *               - 'commonPrefixes': List of common prefixes found for the part.
     *               - 'prefixesPartIncrement': Increments for the generated prefixes, including hierarchical prefixes.
     */
    public function autoCompleteIpn(Part $part, string $description, int $suggestPartDigits): array
    {
        $category = $part->getCategory();
        $ipnSuggestions = ['commonPrefixes' => [], 'prefixesPartIncrement' => []];

        //Show global prefix first if configured
        if ($this->ipnSuggestSettings->globalPrefix !== null && $this->ipnSuggestSettings->globalPrefix !== '') {
            $ipnSuggestions['commonPrefixes'][] = [
                'title' => $this->ipnSuggestSettings->globalPrefix,
                'description' => $this->translator->trans('part.edit.tab.advanced.ipn.prefix.global_prefix')
            ];

            $increment = $this->generateNextPossibleGlobalIncrement();
            $ipnSuggestions['prefixesPartIncrement'][] = [
                'title' => $this->ipnSuggestSettings->globalPrefix . $increment,
                'description' => $this->translator->trans('part.edit.tab.advanced.ipn.prefix.global_prefix')
            ];
        }

        if (strlen($description) > 150) {
            $description = substr($description, 0, 150);
        }

        if ($description !== '' && $this->ipnSuggestSettings->useDuplicateDescription) {
            // Check if the description is already used in another part,

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
        }

        // Validate the category and ensure it's an instance of Category
        if ($category instanceof Category) {
            $currentPath = $category->getPartIpnPrefix();
            $directIpnPrefixEmpty = $category->getPartIpnPrefix() === '';
            $currentPath = $currentPath === '' ? $this->ipnSuggestSettings->fallbackPrefix : $currentPath;

            $increment = $this->generateNextPossiblePartIncrement($currentPath, $part, $suggestPartDigits);

            $ipnSuggestions['commonPrefixes'][] = [
                'title' => $currentPath . $this->ipnSuggestSettings->numberSeparator,
                'description' => $directIpnPrefixEmpty ? $this->translator->trans('part.edit.tab.advanced.ipn.prefix_empty.direct_category', ['%name%' => $category->getName()]) : $this->translator->trans('part.edit.tab.advanced.ipn.prefix.direct_category')
            ];

            $ipnSuggestions['prefixesPartIncrement'][] = [
                'title' => $currentPath . $this->ipnSuggestSettings->numberSeparator . $increment,
                'description' => $directIpnPrefixEmpty ? $this->translator->trans('part.edit.tab.advanced.ipn.prefix_empty.direct_category', ['%name%' => $category->getName()]) : $this->translator->trans('part.edit.tab.advanced.ipn.prefix.direct_category.increment')
            ];

            // Process parent categories
            $parentCategory = $category->getParent();

            while ($parentCategory instanceof Category) {
                // Prepend the parent category's prefix to the current path
                $effectiveIPNPrefix = $parentCategory->getPartIpnPrefix() === '' ? $this->ipnSuggestSettings->fallbackPrefix : $parentCategory->getPartIpnPrefix();

                $currentPath = $effectiveIPNPrefix .  $this->ipnSuggestSettings->categorySeparator . $currentPath;

                $ipnSuggestions['commonPrefixes'][] = [
                    'title' => $currentPath . $this->ipnSuggestSettings->numberSeparator,
                    'description' => $this->translator->trans('part.edit.tab.advanced.ipn.prefix.hierarchical.no_increment')
                ];

                $increment = $this->generateNextPossiblePartIncrement($currentPath, $part, $suggestPartDigits);

                $ipnSuggestions['prefixesPartIncrement'][] = [
                    'title' => $currentPath . $this->ipnSuggestSettings->numberSeparator . $increment,
                    'description' => $this->translator->trans('part.edit.tab.advanced.ipn.prefix.hierarchical.increment')
                ];

                // Move to the next parent category
                $parentCategory = $parentCategory->getParent();
            }
        } elseif ($part->getID() === null) {
            $ipnSuggestions['commonPrefixes'][] = [
                'title' => $this->ipnSuggestSettings->fallbackPrefix,
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

    private function generateNextPossibleGlobalIncrement(): string
    {
        $qb = $this->createQueryBuilder('part');


        $qb->select('part.ipn')
            ->where('REGEXP(part.ipn, :ipnPattern) = TRUE')
            ->setParameter('ipnPattern', '^' . preg_quote($this->ipnSuggestSettings->globalPrefix, '/') . '\d+$')
            ->orderBy('NATSORT(part.ipn)', 'DESC')
            ->setMaxResults(1)
        ;

        $highestIPN = $qb->getQuery()->getOneOrNullResult();
        if ($highestIPN !== null) {
            //Remove the prefix and extract the increment part
            $incrementPart = substr($highestIPN['ipn'], strlen($this->ipnSuggestSettings->globalPrefix));
            //Extract a number using regex
            preg_match('/(\d+)$/', $incrementPart, $matches);
            $incrementInt = isset($matches[1]) ? (int) $matches[1] + 1 : 0;
        } else {
            $incrementInt = 1;
        }


        return str_pad((string) $incrementInt, $this->ipnSuggestSettings->suggestPartDigits, '0', STR_PAD_LEFT);
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
     * @param int $suggestPartDigits The number of digits reserved for the increment.
     *
     * @return string The next possible increment as a zero-padded string.
     *
     * @throws NonUniqueResultException If the query returns non-unique results.
     * @throws NoResultException If the query fails to return a result.
     */
    private function generateNextPossiblePartIncrement(string $currentPath, Part $currentPart, int $suggestPartDigits): string
    {
        $qb = $this->createQueryBuilder('part');

        $expectedLength = strlen($currentPath) + strlen($this->ipnSuggestSettings->categorySeparator) + $suggestPartDigits; // Path + '-' + $suggestPartDigits digits

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

            if ($part->getId() === $currentPart->getId() && $currentPart->getID() !== null) {
                // Extract and return the current part's increment directly
                $incrementPart = substr($part->getIpn(), -$suggestPartDigits);
                if (is_numeric($incrementPart)) {
                    return str_pad((string) $incrementPart, $suggestPartDigits, '0', STR_PAD_LEFT);
                }
            }

            // Extract last $autocompletePartDigits digits for possible available part increment
            $incrementPart = substr($part->getIpn(), -$suggestPartDigits);
            if (is_numeric($incrementPart)) {
                $usedIncrements[] = (int) $incrementPart;
            }

        }

        // Generate the next free $autocompletePartDigits-digit increment
        $nextIncrement = 1; // Start at the beginning

        while (in_array($nextIncrement, $usedIncrements, true)) {
            $nextIncrement++;
        }

        return str_pad((string) $nextIncrement, $suggestPartDigits, '0', STR_PAD_LEFT);
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
        $baseIpn = preg_replace('/_\d+$/', '', $baseIpn); // Remove existing "_ <number>"

        if ($baseIpn === '') {
            return null;
        }

        // Generate next free possible IPN
        return $baseIpn . '_' . ($maxSuffix + 1);
    }

    /**
     * Finds a part based on the provided info provider key and ID, with an option for case sensitivity.
     * If no part is found with the given provider key and ID, null is returned.
     * @param  string  $providerID
     * @param  string|null  $providerKey If null, the provider key will not be included in the search criteria, and only the provider ID will be used for matching.
     * @param  bool  $caseInsensitive  If true, the provider ID comparison will be case-insensitive. Default is true.
     * @return Part|null
     */
    public function getPartByProviderInfo(string $providerID, ?string $providerKey = null, bool $caseInsensitive = true): ?Part
    {
        $qb = $this->createQueryBuilder('part');
        $qb->select('part');

        if ($providerKey) {
            $qb->where("part.providerReference.provider_key = :providerKey");
            $qb->setParameter('providerKey', $providerKey);
        }


        if ($caseInsensitive) {
            $qb->andWhere("LOWER(part.providerReference.provider_id) = LOWER(:providerID)");
        } else {
            $qb->andWhere("part.providerReference.provider_id = :providerID");
        }

        $qb->setParameter('providerID', $providerID);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Finds a part based on the provided MPN (Manufacturer Part Number), with an option for case sensitivity.
     * If no part is found with the given MPN, null is returned.
     * @param  string  $mpn
     * @param  string|null  $manufacturerName If provided, the search will also include a match for the manufacturer's name. If null, the manufacturer name will not be included in the search criteria.
     * @param  bool  $caseInsensitive If true, the MPN comparison will be case-insensitive. Default is true (case-insensitive).
     * @return Part|null
     */
    public function getPartByMPN(string $mpn, ?string $manufacturerName = null, bool $caseInsensitive = true): ?Part
    {
        $qb = $this->createQueryBuilder('part');
        $qb->select('part');

        if ($caseInsensitive) {
            $qb->where("LOWER(part.manufacturer_product_number) = LOWER(:mpn)");
        } else {
            $qb->where("part.manufacturer_product_number = :mpn");
        }

        if ($manufacturerName !== null) {
            $qb->leftJoin('part.manufacturer', 'manufacturer');

            if ($caseInsensitive) {
                $qb->andWhere("LOWER(manufacturer.name) = LOWER(:manufacturerName)");
            } else {
                $qb->andWhere("manufacturer.name = :manufacturerName");
            }
            $qb->setParameter('manufacturerName', $manufacturerName);
        }

        $qb->setParameter('mpn', $mpn);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Finds a part based on the provided SPN (Supplier Part Number), with an option for case sensitivity.
     * If no part is found with the given SPN, null is returned.
     * @param  string  $spn
     * @param  bool  $caseInsensitive
     * @return Part|null
     */
    public function getPartBySPN(string $spn, bool $caseInsensitive = true): ?Part
    {
        $qb = $this->createQueryBuilder('part');
        $qb->select('part');

        $qb->leftJoin('part.orderdetails', 'o');

        if ($caseInsensitive) {
            $qb->where("LOWER(o.supplierpartnr) = LOWER(:spn)");
        } else {
            $qb->where("o.supplierpartnr = :spn");
        }

        $qb->setParameter('spn', $spn);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
