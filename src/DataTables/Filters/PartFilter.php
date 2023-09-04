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
use App\DataTables\Filters\Constraints\Part\LessThanDesiredConstraint;
use App\DataTables\Filters\Constraints\Part\ParameterConstraint;
use App\DataTables\Filters\Constraints\Part\TagsConstraint;
use App\DataTables\Filters\Constraints\TextConstraint;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
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
        //We have to use Having here, as we use an alias column which is not supported on the where clause and would result in an error
        $this->amountSum = (new IntConstraint('amountSum'))->useHaving();
        $this->lotCount = new IntConstraint('COUNT(partLots)');
        $this->lessThanDesired = new LessThanDesiredConstraint();

        $this->storelocation = new EntityConstraint($nodesListBuilder, StorageLocation::class, 'partLots.storage_location');
        $this->lotNeedsRefill = new BooleanConstraint('partLots.needs_refill');
        $this->lotUnknownAmount = new BooleanConstraint('partLots.instock_unknown');
        $this->lotExpirationDate = new DateTimeConstraint('partLots.expiration_date');
        $this->lotDescription = new TextConstraint('partLots.description');
        $this->lotOwner = new EntityConstraint($nodesListBuilder, User::class, 'partLots.owner');

        $this->manufacturer = new EntityConstraint($nodesListBuilder, Manufacturer::class, 'part.manufacturer');
        $this->manufacturer_product_number = new TextConstraint('part.manufacturer_product_number');
        $this->manufacturer_product_url = new TextConstraint('part.manufacturer_product_url');
        $this->manufacturing_status = new ChoiceConstraint('part.manufacturing_status');

        $this->attachmentsCount = new IntConstraint('COUNT(attachments)');
        $this->attachmentType = new EntityConstraint($nodesListBuilder, AttachmentType::class, 'attachments.attachment_type');
        $this->attachmentName = new TextConstraint('attachments.name');

        $this->supplier = new EntityConstraint($nodesListBuilder, Supplier::class, 'orderdetails.supplier');
        $this->orderdetailsCount = new IntConstraint('COUNT(orderdetails)');
        $this->obsolete = new BooleanConstraint('orderdetails.obsolete');

        $this->parameters = new ArrayCollection();
        $this->parametersCount = new IntConstraint('COUNT(parameters)');
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $this->applyAllChildFilters($queryBuilder);
    }
}
