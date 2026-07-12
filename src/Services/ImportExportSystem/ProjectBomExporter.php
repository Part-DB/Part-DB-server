<?php

declare(strict_types=1);

namespace App\Services\ImportExportSystem;

use App\Entity\Parts\Part;
use App\Entity\Parts\StorageLocation;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Services\Formatters\AmountFormatter;
use App\Services\Formatters\MoneyFormatter;
use App\Services\ProjectSystem\ProjectBuildHelper;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ProjectBomExporter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AmountFormatter $amountFormatter,
        private MoneyFormatter $moneyFormatter,
        private ProjectBuildHelper $projectBuildHelper,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Load the requested BOM entries and return them in exactly the same
     * order as the supplied ID list.
     *
     * IDs which do not belong to the supplied project are ignored.
     *
     * @param list<int> $orderedIds
     *
     * @return list<ProjectBOMEntry>
     */
    public function getOrderedEntries(
        Project $project,
        array $orderedIds,
    ): array {
        if ($orderedIds === []) {
            return [];
        }

        $entries = $this->entityManager
            ->createQueryBuilder()
            ->select('bom_entry')
            ->addSelect('part')
            ->addSelect('category')
            ->addSelect('footprint')
            ->addSelect('manufacturer')
            ->addSelect('partLots')
            ->addSelect('storageLocation')
            ->from(ProjectBOMEntry::class, 'bom_entry')
            ->leftJoin('bom_entry.part', 'part')
            ->leftJoin('part.category', 'category')
            ->leftJoin('part.footprint', 'footprint')
            ->leftJoin('part.manufacturer', 'manufacturer')
            ->leftJoin('part.partLots', 'partLots')
            ->leftJoin('partLots.storage_location', 'storageLocation')
            ->where('bom_entry.project = :project')
            ->andWhere('bom_entry.id IN (:ids)')
            ->setParameter('project', $project)
            ->setParameter('ids', $orderedIds)
            ->getQuery()
            ->getResult();

        $entriesById = [];

        foreach ($entries as $entry) {
            if (!$entry instanceof ProjectBOMEntry) {
                continue;
            }

            $entriesById[$entry->getID()] = $entry;
        }

        $orderedEntries = [];

        foreach ($orderedIds as $id) {
            if (isset($entriesById[$id])) {
                $orderedEntries[] = $entriesById[$id];
            }
        }

        return $orderedEntries;
    }

    /**
     * Generate a CSV row from raw entity values.
     *
     * @param list<string> $columns
     * @param list<string> $labels
     *
     * @return array<string, string|int|float|null>
     */
    public function createRow(
        ProjectBOMEntry $entry,
        array $columns,
        array $labels,
    ): array {
        $row = [];

        foreach ($columns as $index => $column) {
            $label = $labels[$index] ?? $column;

            $row[$label] = $this->getColumnValue($entry, $column);
        }

        return $row;
    }

    private function getColumnValue(
        ProjectBOMEntry $entry,
        string $column,
    ): string|int|float|null {
        $part = $entry->getPart();

        return match ($column) {
            'id' => $entry->getID(),

            'quantity' => $this->formatQuantity($entry),

            'partId' => $part?->getID(),

            'name' => $this->formatName($entry),

            'ipn' => $part?->getIpn() ?? '',

            'description' => $part instanceof Part
                ? $part->getDescription()
                : $entry->getComment(),

            'category' => $part?->getCategory()?->getFullPath() ?? '',

            'footprint' => $part?->getFootprint()?->getFullPath() ?? '',

            'manufacturer' => $part?->getManufacturer()?->getName() ?? '',

            'manufacturing_status' => $this->formatManufacturingStatus(
                $part
            ),

            'mountnames' => $this->formatMountNames($entry),

            'instockAmount' => $this->formatInStockAmount($part),

            'storelocation' => $this->formatStorageLocations($part),

            'price' => $this->formatUnitPrice($entry),

            'ext_price' => $this->formatExtendedPrice($entry),

            'addedDate' => $entry->getAddedDate()?->format(DATE_ATOM) ?? '',

            'lastModified' => $entry->getLastModified()?->format(DATE_ATOM)
                ?? '',

            default => '',
        };
    }

    private function formatQuantity(ProjectBOMEntry $entry): float|string
    {
        $part = $entry->getPart();

        if (!$part instanceof Part) {
            return round($entry->getQuantity());
        }

        return $this->amountFormatter->format(
            $entry->getQuantity(),
            $part->getPartUnit(),
        );
    }

    private function formatName(ProjectBOMEntry $entry): string
    {
        $part = $entry->getPart();

        if (!$part instanceof Part) {
            return trim((string) $entry->getName());
        }

        $values = [$part->getName()];

        /*
         * The DataTable displays the optional BOM entry name beneath the
         * linked part's name. Preserve that information with a separating
         * space in CSV.
         */
        if ($entry->getName() !== null && $entry->getName() !== '') {
            $values[] = $entry->getName();
        }

        return implode(' ', $values);
    }

    private function formatManufacturingStatus(?Part $part): string
    {
        $status = $part?->getManufacturingStatus();

        if ($status === null) {
            return '';
        }

        return $this->translator->trans(
            $status->toTranslationKey()
        );
    }

    private function formatMountNames(ProjectBOMEntry $entry): string
    {
        $mountNames = array_map(
            static fn(string $value): string => trim($value),
            explode(',', $entry->getMountnames()),
        );

        $mountNames = array_filter(
            $mountNames,
            static fn(string $value): bool => $value !== '',
        );

        return implode(', ', $mountNames);
    }

    private function formatInStockAmount(?Part $part): string
    {
        if (!$part instanceof Part) {
            return '';
        }

        $amount = $this->amountFormatter->format(
            $part->getAmountSum(),
            $part->getPartUnit(),
        );

        if ($part->isAmountUnknown()) {
            $amount = $part->getAmountSum() === 0.0
                ? '?'
                : '≥'.$amount;
        }

        $expiredAmount = $part->getExpiredAmountSum();

        if ($expiredAmount > 0) {
            $amount .= sprintf(
                ' (+%s expired)',
                $this->amountFormatter->format(
                    $expiredAmount,
                    $part->getPartUnit(),
                ),
            );
        }

        return $amount;
    }

    private function formatStorageLocations(?Part $part): string
    {
        if (!$part instanceof Part) {
            return '';
        }

        $locations = [];

        foreach ($part->getPartLots() as $partLot) {
            $storageLocation = $partLot->getStorageLocation();

            if (!$storageLocation instanceof StorageLocation) {
                continue;
            }

            $locations[$storageLocation->getID()] =
                $storageLocation->getFullPath();
        }

        return implode(', ', array_values($locations));
    }

    private function formatUnitPrice(ProjectBOMEntry $entry): string
    {
        $price = $this->projectBuildHelper->getEntryUnitPrice($entry);

        return $this->moneyFormatter->format(
            $price->toScale(2, RoundingMode::Up)->toFloat(),
            null,
            2,
            true,
        );
    }

    private function formatExtendedPrice(
        ProjectBOMEntry $entry,
    ): string {
        $price = $this->projectBuildHelper->getEntryUnitPrice($entry);

        $extendedPrice = $price->multipliedBy(
            BigDecimal::fromFloatShortest($entry->getQuantity())
        );

        return $this->moneyFormatter->format(
            $extendedPrice
                ->toScale(2, RoundingMode::Up)
                ->toFloat(),
            null,
            2,
            true,
        );
    }
}
