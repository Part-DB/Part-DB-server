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
use App\DataTables\Filters\Constraints\ChoiceConstraint;
use App\DataTables\Filters\Constraints\DateTimeConstraint;
use App\DataTables\Filters\Constraints\EntityConstraint;
use App\DataTables\Filters\Constraints\NumberConstraint;
use App\DataTables\Filters\Constraints\Part\ParameterConstraint;
use App\DataTables\Filters\Constraints\TextConstraint;
use App\DataTables\Filters\PartFilter;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartCustomState;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\ProjectSystem\Project;
use App\Mcp\DTO\AdvancedPartSearchInput;
use App\Mcp\DTO\Filters\DateTimeFilterInput;
use App\Mcp\DTO\Filters\EntityFilterInput;
use App\Mcp\DTO\Filters\NumberFilterInput;
use App\Mcp\DTO\Filters\TextFilterInput;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Backs the advanced_search_parts MCP tool: applies an AdvancedPartSearchInput onto a fresh PartFilter (the same
 * constraint system that powers the "Filters" tab of the part table in the web UI, see PartFilter) and runs it.
 */
final class AdvancedSearchPartsProcessor implements ProcessorInterface
{
    use McpToolErrorHandling;

    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 200;

    /** @var array<string, string> Allowed orderBy values mapped to their DQL property */
    private const ORDER_BY_FIELDS = [
        'name' => 'part.name',
        'id' => 'part.id',
        'lastModified' => 'part.lastModified',
        'addedDate' => 'part.addedDate',
        'minAmount' => 'part.minamount',
        'mass' => 'part.mass',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NodesListBuilder $nodesListBuilder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        return $this->runCatchingExpectedErrors(function () use ($data) {
            if (!$data instanceof AdvancedPartSearchInput) {
                throw new \InvalidArgumentException('Expected AdvancedPartSearchInput');
            }

            $filter = new PartFilter($this->nodesListBuilder);
            $this->applyFilters($filter, $data);

            $qb = $this->entityManager->getRepository(Part::class)->createQueryBuilder('part');
            try {
                //The constraint classes behind PartFilter throw a plain RuntimeException for an invalid
                //operator/missing BETWEEN bound - translate that into the same caller-actionable error reporting
                //as everything else here (RuntimeException isn't in McpToolErrorHandling's catch list on purpose,
                //since most callers of it never route unvalidated user input through this constraint system).
                $filter->apply($qb);
            } catch (\RuntimeException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
            $this->addJoins($qb);
            $qb->addGroupBy('part');

            $this->applyOrdering($qb, $data);

            $limit = $data->limit ?? self::DEFAULT_LIMIT;
            if ($limit < 1 || $limit > self::MAX_LIMIT) {
                throw new BadRequestHttpException(sprintf('limit must be between 1 and %d.', self::MAX_LIMIT));
            }
            $qb->setMaxResults($limit);

            return $qb->getQuery()->getResult();
        });
    }

    private function applyFilters(PartFilter $filter, AdvancedPartSearchInput $data): void
    {
        $this->applyText($filter->name, $data->name);
        $this->applyText($filter->description, $data->description);
        $this->applyText($filter->comment, $data->comment);
        $this->applyText($filter->ipn, $data->ipn);
        $this->applyText($filter->gtin, $data->gtin);
        $this->applyText($filter->manufacturer_product_number, $data->manufacturerProductNumber);
        $this->applyText($filter->manufacturer_product_url, $data->manufacturerProductUrl);
        $this->applyText($filter->lotDescription, $data->lotDescription);
        $this->applyText($filter->attachmentName, $data->attachmentName);

        if ($data->tags !== null) {
            $filter->tags->setValue(implode(',', $data->tags->tags))->setOperator($data->tags->operator);
        }

        if ($data->manufacturingStatus !== null) {
            $this->applyChoice($filter->manufacturing_status, $data->manufacturingStatus->operator, $data->manufacturingStatus->value);
        }

        $this->applyNumber($filter->minAmount, $data->minAmount);
        $this->applyNumber($filter->mass, $data->mass);
        $this->applyNumber($filter->amountSum, $data->amountSum);
        $this->applyNumber($filter->lotCount, $data->lotCount);
        $this->applyNumber($filter->orderdetailsCount, $data->orderdetailsCount);
        $this->applyNumber($filter->attachmentsCount, $data->attachmentsCount);
        $this->applyNumber($filter->parametersCount, $data->parametersCount);

        $filter->favorite->setValue($data->favorite);
        $filter->needsReview->setValue($data->needsReview);
        $filter->obsolete->setValue($data->obsolete);
        $filter->lessThanDesired->setValue($data->lessThanDesired);
        $filter->lotNeedsRefill->setValue($data->lotNeedsRefill);
        $filter->lotUnknownAmount->setValue($data->lotUnknownAmount);

        $this->applyDateTime($filter->lastModified, $data->lastModified, 'lastModified');
        $this->applyDateTime($filter->addedDate, $data->addedDate, 'addedDate');
        $this->applyDateTime($filter->lotExpirationDate, $data->lotExpirationDate, 'lotExpirationDate');

        $this->applyEntity($filter->category, $data->category, Category::class);
        $this->applyEntity($filter->footprint, $data->footprint, Footprint::class);
        $this->applyEntity($filter->manufacturer, $data->manufacturer, Manufacturer::class);
        $this->applyEntity($filter->storelocation, $data->storelocation, StorageLocation::class);
        $this->applyEntity($filter->supplier, $data->supplier, Supplier::class);
        $this->applyEntity($filter->measurementUnit, $data->measurementUnit, MeasurementUnit::class);
        $this->applyEntity($filter->partCustomState, $data->partCustomState, PartCustomState::class);
        $this->applyEntity($filter->attachmentType, $data->attachmentType, AttachmentType::class);
        $this->applyEntity($filter->project, $data->project, Project::class);

        foreach ($data->parameters as $parameterInput) {
            $constraint = new ParameterConstraint();

            if ($parameterInput->name !== null) {
                $constraint->setName($parameterInput->name);
            }
            if ($parameterInput->symbol !== null) {
                $constraint->setSymbol($parameterInput->symbol);
            }
            if ($parameterInput->unit !== null) {
                $constraint->setUnit($parameterInput->unit);
            }
            if ($parameterInput->operator !== null) {
                $constraint->getValue()->setOperator($parameterInput->operator);
                $constraint->getValue()->setValue1($parameterInput->value);
                $constraint->getValue()->setValue2($parameterInput->value2);
            }
            if ($parameterInput->valueText !== null) {
                $constraint->getValueText()->setValue($parameterInput->valueText)->setOperator('=');
            }

            $filter->parameters->add($constraint);
        }
    }

    private function applyText(TextConstraint $constraint, ?TextFilterInput $input): void
    {
        if ($input === null) {
            return;
        }

        $constraint->setValue($input->value)->setOperator($input->operator);
    }

    private function applyNumber(NumberConstraint $constraint, ?NumberFilterInput $input): void
    {
        if ($input === null) {
            return;
        }

        $constraint->setValue1($input->value);
        $constraint->setOperator($input->operator);
        $constraint->setValue2($input->value2);
    }

    private function applyChoice(ChoiceConstraint $constraint, string $operator, array $value): void
    {
        $constraint->setValue($value)->setOperator($operator);
    }

    private function applyDateTime(DateTimeConstraint $constraint, ?DateTimeFilterInput $input, string $fieldName): void
    {
        if ($input === null) {
            return;
        }

        $constraint->setValue1($this->parseDateTime($input->value, $fieldName));
        $constraint->setOperator($input->operator);
        if ($input->value2 !== null) {
            $constraint->setValue2($this->parseDateTime($input->value2, $fieldName));
        }
    }

    private function parseDateTime(string $value, string $fieldName): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new BadRequestHttpException(sprintf('Invalid date/time value "%s" for filter "%s": %s', $value, $fieldName, $e->getMessage()));
        }
    }

    /**
     * @param class-string<AbstractDBElement> $class
     */
    private function applyEntity(EntityConstraint $constraint, ?EntityFilterInput $input, string $class): void
    {
        if ($input === null) {
            return;
        }

        //EntityConstraint::setValue() rejects null (its instanceof check is not null-safe despite the nullable
        //type-hint), so only call it when an id was actually given - the constraint's value already defaults to
        //null, which is exactly what "=" / "!=" without an id need to match "has none set" / "has any set".
        if ($input->id !== null) {
            $value = $this->entityManager->find($class, $input->id);
            if (!$value instanceof AbstractDBElement) {
                throw new NotFoundHttpException(sprintf('%s with id %d not found', (new \ReflectionClass($class))->getShortName(), $input->id));
            }

            $constraint->setValue($value);
        }

        $constraint->setOperator($input->operator);
    }

    private function applyOrdering(QueryBuilder $qb, AdvancedPartSearchInput $data): void
    {
        $orderBy = $data->orderBy ?? 'name';
        if (!isset(self::ORDER_BY_FIELDS[$orderBy])) {
            throw new BadRequestHttpException(sprintf('Invalid orderBy "%s". Valid values are: %s', $orderBy, implode(', ', array_keys(self::ORDER_BY_FIELDS))));
        }

        $direction = $data->orderDirection ?? 'ASC';
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new BadRequestHttpException(sprintf('Invalid orderDirection "%s". Valid values are: ASC, DESC', $direction));
        }

        $qb->addOrderBy(self::ORDER_BY_FIELDS[$orderBy], $direction);
    }

    /**
     * Adds only the joins actually referenced by the constraints PartFilter may have generated DQL for - the same
     * "check the alias appears in the generated DQL string" trick SearchPartsProcessor uses, extended to cover
     * every alias the fields exposed by AdvancedPartSearchInput can reference (see PartsDataTable::addJoins for
     * the full reference list this is a reduced copy of).
     */
    private function addJoins(QueryBuilder $qb): void
    {
        $dql = $qb->getDQL();

        if (str_contains($dql, '_category')) {
            $qb->leftJoin('part.category', '_category');
        }
        if (str_contains($dql, '_partLots') || str_contains($dql, '_storelocations')) {
            $qb->leftJoin('part.partLots', '_partLots');
            $qb->leftJoin('_partLots.storage_location', '_storelocations');
        }
        if (str_contains($dql, '_footprint')) {
            $qb->leftJoin('part.footprint', '_footprint');
        }
        if (str_contains($dql, '_manufacturer')) {
            $qb->leftJoin('part.manufacturer', '_manufacturer');
        }
        if (str_contains($dql, '_orderdetails') || str_contains($dql, '_suppliers')) {
            $qb->leftJoin('part.orderdetails', '_orderdetails');
            $qb->leftJoin('_orderdetails.supplier', '_suppliers');
        }
        if (str_contains($dql, '_attachments')) {
            $qb->leftJoin('part.attachments', '_attachments');
        }
        if (str_contains($dql, '_partUnit')) {
            $qb->leftJoin('part.partUnit', '_partUnit');
        }
        if (str_contains($dql, '_partCustomState')) {
            $qb->leftJoin('part.partCustomState', '_partCustomState');
        }
        if (str_contains($dql, '_parameters')) {
            $qb->leftJoin('part.parameters', '_parameters');
        }
        if (str_contains($dql, '_projectBomEntries')) {
            $qb->leftJoin('part.project_bom_entries', '_projectBomEntries');
        }
    }
}
