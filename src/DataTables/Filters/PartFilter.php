<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\DataTables\Filters;

use App\DataTables\Filters\Constraints\BooleanConstraint;
use App\DataTables\Filters\Constraints\ChoiceConstraint;
use App\DataTables\Filters\Constraints\DateTimeConstraint;
use App\DataTables\Filters\Constraints\EntityConstraint;
use App\DataTables\Filters\Constraints\IntConstraint;
use App\DataTables\Filters\Constraints\NumberConstraint;
use App\DataTables\Filters\Constraints\Part\BulkImportJobExistsConstraint;
use App\DataTables\Filters\Constraints\Part\BulkImportJobStatusConstraint;
use App\DataTables\Filters\Constraints\Part\BulkImportPartStatusConstraint;
use App\DataTables\Filters\Constraints\Part\LessThanDesiredConstraint;
use App\DataTables\Filters\Constraints\Part\ParameterConstraint;
use App\DataTables\Filters\Constraints\Part\TagsConstraint;
use App\DataTables\Filters\Constraints\TextConstraint;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\ProjectSystem\Project;
use App\Entity\UserSystem\User;
use App\Services\Trees\NodesListBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;

class PartFilter implements FilterInterface
{

    use CompoundFilterTrait;

    public readonly IntConstraint $dbId;
    public readonly TextConstraint $ipn;
    public readonly TextConstraint $name;
    public readonly TextConstraint $description;
    public readonly TextConstraint $comment;
    public readonly TagsConstraint $tags;
    public readonly NumberConstraint $minAmount;
    public readonly BooleanConstraint $favorite;
    public readonly BooleanConstraint $needsReview;
    public readonly NumberConstraint $mass;
    public readonly DateTimeConstraint $lastModified;
    public readonly DateTimeConstraint $addedDate;
    public readonly EntityConstraint $category;
    public readonly EntityConstraint $footprint;
    public readonly EntityConstraint $manufacturer;
    public readonly ChoiceConstraint $manufacturing_status;
    public readonly EntityConstraint $supplier;
    public readonly IntConstraint $orderdetailsCount;
    public readonly BooleanConstraint $obsolete;
    public readonly EntityConstraint $storelocation;
    public readonly IntConstraint $lotCount;
    public readonly IntConstraint $amountSum;
    public readonly LessThanDesiredConstraint $lessThanDesired;

    public readonly BooleanConstraint $lotNeedsRefill;
    public readonly TextConstraint $lotDescription;
    public readonly BooleanConstraint $lotUnknownAmount;
    public readonly DateTimeConstraint $lotExpirationDate;
    public readonly EntityConstraint $lotOwner;

    public readonly EntityConstraint $measurementUnit;
    public readonly TextConstraint $manufacturer_product_url;
    public readonly TextConstraint $manufacturer_product_number;
    public readonly IntConstraint $attachmentsCount;
    public readonly EntityConstraint $attachmentType;
    public readonly TextConstraint $attachmentName;

    /** @var ArrayCollection<int, ParameterConstraint> */
    public readonly ArrayCollection $parameters;
    public readonly IntConstraint $parametersCount;

    /*************************************************
     * Project tab
     *************************************************/

    public readonly EntityConstraint $project;
    public readonly NumberConstraint $bomQuantity;
    public readonly TextConstraint $bomName;
    public readonly TextConstraint $bomComment;

    /*************************************************
     * Bulk Import Job tab
     *************************************************/

    public readonly BulkImportJobExistsConstraint $inBulkImportJob;
    public readonly BulkImportJobStatusConstraint $bulkImportJobStatus;
    public readonly BulkImportPartStatusConstraint $bulkImportPartStatus;

    public function __construct(NodesListBuilder $nodesListBuilder)
    {
        $this->name = new TextConstraint('part.name');
        $this->description = new TextConstraint('part.description');
        $this->comment = new TextConstraint('part.comment');
        $this->category = new EntityConstraint($nodesListBuilder, Category::class, 'part.category');
        $this->footprint = new EntityConstraint($nodesListBuilder, Footprint::class, 'part.footprint');
        $this->tags = new TagsConstraint('part.tags');

        $this->favorite = new BooleanConstraint('part.favorite');
        $this->needsReview = new BooleanConstraint('part.needs_review');
        $this->measurementUnit = new EntityConstraint($nodesListBuilder, MeasurementUnit::class, 'part.partUnit');
        $this->mass = new NumberConstraint('part.mass');
        $this->dbId = new IntConstraint('part.id');
        $this->ipn = new TextConstraint('part.ipn');
        $this->addedDate = new DateTimeConstraint('part.addedDate');
        $this->lastModified = new DateTimeConstraint('part.lastModified');

        $this->minAmount = new NumberConstraint('part.minamount');
        /* We have to use an IntConstraint here because otherwise we get just an empty result list when applying the filter
           This seems to be related to the fact, that PDO does not have an float parameter type and using string type does not work in this situation (at least in SQLite)
           TODO: Find a better solution here
         */
        $this->amountSum = (new IntConstraint('(
                    SELECT COALESCE(SUM(__partLot.amount), 0.0)
                    FROM ' . PartLot::class . ' __partLot
                    WHERE __partLot.part = part.id
                    AND __partLot.instock_unknown = false
                    AND (__partLot.expiration_date IS NULL OR __partLot.expiration_date > CURRENT_DATE())
                )', identifier: "amountSumWhere"));
        $this->lotCount = new IntConstraint('COUNT(_partLots)');
        $this->lessThanDesired = new LessThanDesiredConstraint();

        $this->storelocation = new EntityConstraint($nodesListBuilder, StorageLocation::class, '_partLots.storage_location');
        $this->lotNeedsRefill = new BooleanConstraint('_partLots.needs_refill');
        $this->lotUnknownAmount = new BooleanConstraint('_partLots.instock_unknown');
        $this->lotExpirationDate = new DateTimeConstraint('_partLots.expiration_date');
        $this->lotDescription = new TextConstraint('_partLots.description');
        $this->lotOwner = new EntityConstraint($nodesListBuilder, User::class, '_partLots.owner');

        $this->manufacturer = new EntityConstraint($nodesListBuilder, Manufacturer::class, 'part.manufacturer');
        $this->manufacturer_product_number = new TextConstraint('part.manufacturer_product_number');
        $this->manufacturer_product_url = new TextConstraint('part.manufacturer_product_url');
        $this->manufacturing_status = new ChoiceConstraint('part.manufacturing_status');

        $this->attachmentsCount = new IntConstraint('COUNT(_attachments)');
        $this->attachmentType = new EntityConstraint($nodesListBuilder, AttachmentType::class, '_attachments.attachment_type');
        $this->attachmentName = new TextConstraint('_attachments.name');

        $this->supplier = new EntityConstraint($nodesListBuilder, Supplier::class, '_orderdetails.supplier');
        $this->orderdetailsCount = new IntConstraint('COUNT(_orderdetails)');
        $this->obsolete = new BooleanConstraint('_orderdetails.obsolete');

        $this->parameters = new ArrayCollection();
        $this->parametersCount = new IntConstraint('COUNT(_parameters)');

        $this->project = new EntityConstraint($nodesListBuilder, Project::class, '_projectBomEntries.project');
        $this->bomQuantity = new NumberConstraint('_projectBomEntries.quantity');
        $this->bomName = new TextConstraint('_projectBomEntries.name');
        $this->bomComment = new TextConstraint('_projectBomEntries.comment');

        // Bulk Import Job filters
        $this->inBulkImportJob = new BulkImportJobExistsConstraint();
        $this->bulkImportJobStatus = new BulkImportJobStatusConstraint();
        $this->bulkImportPartStatus = new BulkImportPartStatusConstraint();

    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $this->applyAllChildFilters($queryBuilder);
    }
}
