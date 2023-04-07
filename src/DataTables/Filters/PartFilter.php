<?php
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
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Services\Trees\NodesListBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;
use Svg\Tag\Text;

class PartFilter implements FilterInterface
{

    use CompoundFilterTrait;

    protected IntConstraint $dbId;
    protected TextConstraint $ipn;
    protected TextConstraint $name;
    protected TextConstraint $description;
    protected TextConstraint $comment;
    protected TagsConstraint $tags;
    protected NumberConstraint $minAmount;
    protected BooleanConstraint $favorite;
    protected BooleanConstraint $needsReview;
    protected NumberConstraint $mass;
    protected DateTimeConstraint $lastModified;
    protected DateTimeConstraint $addedDate;
    protected EntityConstraint $category;
    protected EntityConstraint $footprint;
    protected EntityConstraint $manufacturer;
    protected ChoiceConstraint $manufacturing_status;
    protected EntityConstraint $supplier;
    protected IntConstraint $orderdetailsCount;
    protected BooleanConstraint $obsolete;
    protected EntityConstraint $storelocation;
    protected IntConstraint $lotCount;
    protected IntConstraint $amountSum;
    protected LessThanDesiredConstraint $lessThanDesired;

    /**
     * @return LessThanDesiredConstraint
     */
    public function getLessThanDesired(): LessThanDesiredConstraint
    {
        return $this->lessThanDesired;
    }
    protected BooleanConstraint $lotNeedsRefill;
    protected TextConstraint $lotDescription;
    protected BooleanConstraint $lotUnknownAmount;
    protected DateTimeConstraint $lotExpirationDate;
    protected EntityConstraint $measurementUnit;
    protected TextConstraint $manufacturer_product_url;
    protected TextConstraint $manufacturer_product_number;
    protected IntConstraint $attachmentsCount;
    protected EntityConstraint $attachmentType;
    protected TextConstraint $attachmentName;
    /** @var ArrayCollection<int, ParameterConstraint> */
    protected ArrayCollection $parameters;
    protected IntConstraint $parametersCount;

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

        $this->storelocation = new EntityConstraint($nodesListBuilder, Storelocation::class, 'partLots.storage_location');
        $this->lotNeedsRefill = new BooleanConstraint('partLots.needs_refill');
        $this->lotUnknownAmount = new BooleanConstraint('partLots.instock_unknown');
        $this->lotExpirationDate = new DateTimeConstraint('partLots.expiration_date');
        $this->lotDescription = new TextConstraint('partLots.description');

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


    /**
     * @return BooleanConstraint|false
     */
    public function getFavorite()
    {
        return $this->favorite;
    }

    /**
     * @return BooleanConstraint
     */
    public function getNeedsReview(): BooleanConstraint
    {
        return $this->needsReview;
    }

    public function getMass(): NumberConstraint
    {
        return $this->mass;
    }

    public function getName(): TextConstraint
    {
        return $this->name;
    }

    public function getDescription(): TextConstraint
    {
        return $this->description;
    }

    /**
     * @return DateTimeConstraint
     */
    public function getLastModified(): DateTimeConstraint
    {
        return $this->lastModified;
    }

    /**
     * @return DateTimeConstraint
     */
    public function getAddedDate(): DateTimeConstraint
    {
        return $this->addedDate;
    }

    public function getCategory(): EntityConstraint
    {
        return $this->category;
    }

    /**
     * @return EntityConstraint
     */
    public function getFootprint(): EntityConstraint
    {
        return $this->footprint;
    }

    /**
     * @return EntityConstraint
     */
    public function getManufacturer(): EntityConstraint
    {
        return $this->manufacturer;
    }

    /**
     * @return EntityConstraint
     */
    public function getSupplier(): EntityConstraint
    {
        return $this->supplier;
    }

    /**
     * @return EntityConstraint
     */
    public function getStorelocation(): EntityConstraint
    {
        return $this->storelocation;
    }

    /**
     * @return EntityConstraint
     */
    public function getMeasurementUnit(): EntityConstraint
    {
        return $this->measurementUnit;
    }

    /**
     * @return NumberConstraint
     */
    public function getDbId(): NumberConstraint
    {
        return $this->dbId;
    }

    public function getIpn(): TextConstraint
    {
        return $this->ipn;
    }

    /**
     * @return TextConstraint
     */
    public function getComment(): TextConstraint
    {
        return $this->comment;
    }

    /**
     * @return NumberConstraint
     */
    public function getMinAmount(): NumberConstraint
    {
        return $this->minAmount;
    }

    /**
     * @return TextConstraint
     */
    public function getManufacturerProductUrl(): TextConstraint
    {
        return $this->manufacturer_product_url;
    }

    /**
     * @return TextConstraint
     */
    public function getManufacturerProductNumber(): TextConstraint
    {
        return $this->manufacturer_product_number;
    }

    public function getLotCount(): NumberConstraint
    {
        return $this->lotCount;
    }

    /**
     * @return TagsConstraint
     */
    public function getTags(): TagsConstraint
    {
        return $this->tags;
    }

    /**
     * @return IntConstraint
     */
    public function getOrderdetailsCount(): IntConstraint
    {
        return $this->orderdetailsCount;
    }

    /**
     * @return IntConstraint
     */
    public function getAttachmentsCount(): IntConstraint
    {
        return $this->attachmentsCount;
    }

    /**
     * @return BooleanConstraint
     */
    public function getLotNeedsRefill(): BooleanConstraint
    {
        return $this->lotNeedsRefill;
    }

    /**
     * @return BooleanConstraint
     */
    public function getLotUnknownAmount(): BooleanConstraint
    {
        return $this->lotUnknownAmount;
    }

    /**
     * @return DateTimeConstraint
     */
    public function getLotExpirationDate(): DateTimeConstraint
    {
        return $this->lotExpirationDate;
    }

    /**
     * @return EntityConstraint
     */
    public function getAttachmentType(): EntityConstraint
    {
        return $this->attachmentType;
    }

    /**
     * @return TextConstraint
     */
    public function getAttachmentName(): TextConstraint
    {
        return $this->attachmentName;
    }

    public function getManufacturingStatus(): ChoiceConstraint
    {
        return $this->manufacturing_status;
    }

    public function getAmountSum(): NumberConstraint
    {
        return $this->amountSum;
    }

    /**
     * @return ArrayCollection
     */
    public function getParameters(): ArrayCollection
    {
        return $this->parameters;
    }

    public function getParametersCount(): IntConstraint
    {
        return $this->parametersCount;
    }

    /**
     * @return TextConstraint
     */
    public function getLotDescription(): TextConstraint
    {
        return $this->lotDescription;
    }

    /**
     * @return BooleanConstraint
     */
    public function getObsolete(): BooleanConstraint
    {
        return $this->obsolete;
    }




}
