<?php

namespace App\DataTables\Filters;

use App\DataTables\Filters\Constraints\BooleanConstraint;
use App\DataTables\Filters\Constraints\ChoiceConstraint;
use App\DataTables\Filters\Constraints\DateTimeConstraint;
use App\DataTables\Filters\Constraints\EntityConstraint;
use App\DataTables\Filters\Constraints\IntConstraint;
use App\DataTables\Filters\Constraints\NumberConstraint;
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
use Doctrine\ORM\QueryBuilder;
use Svg\Tag\Text;

class PartFilter implements FilterInterface
{

    use CompoundFilterTrait;

    /** @var NumberConstraint */
    protected $dbId;

    /** @var TextConstraint */
    protected $name;

    /** @var TextConstraint */
    protected $description;

    /** @var TextConstraint */
    protected $comment;

    /** @var TagsConstraint */
    protected $tags;

    /** @var NumberConstraint */
    protected $minAmount;

    /** @var BooleanConstraint */
    protected $favorite;

    /** @var BooleanConstraint */
    protected $needsReview;

    /** @var NumberConstraint */
    protected $mass;

    /** @var DateTimeConstraint */
    protected $lastModified;

    /** @var DateTimeConstraint */
    protected $addedDate;

    /** @var EntityConstraint */
    protected $category;

    /** @var EntityConstraint */
    protected $footprint;

    /** @var EntityConstraint */
    protected $manufacturer;

    /** @var ChoiceConstraint */
    protected $manufacturing_status;

    /** @var EntityConstraint */
    protected $supplier;

    /** @var IntConstraint */
    protected $orderdetailsCount;

    /** @var EntityConstraint */
    protected $storelocation;

    /** @var IntConstraint */
    protected $lotCount;

    /** @var BooleanConstraint */
    protected $lotNeedsRefill;

    /** @var BooleanConstraint */
    protected $lotUnknownAmount;

    /** @var DateTimeConstraint */
    protected $lotExpirationDate;

    /** @var EntityConstraint */
    protected $measurementUnit;

    /** @var TextConstraint */
    protected $manufacturer_product_url;

    /** @var TextConstraint */
    protected $manufacturer_product_number;

    /** @var IntConstraint */
    protected $attachmentsCount;

    /** @var EntityConstraint */
    protected $attachmentType;

    /** @var TextConstraint */
    protected $attachmentName;

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
        $this->addedDate = new DateTimeConstraint('part.addedDate');
        $this->lastModified = new DateTimeConstraint('part.lastModified');

        $this->minAmount = new NumberConstraint('part.minAmount');
        $this->lotCount = new IntConstraint('COUNT(partLots)');
        $this->supplier = new EntityConstraint($nodesListBuilder, Supplier::class, 'orderdetails.supplier');
        $this->lotNeedsRefill = new BooleanConstraint('partLots.needs_refill');
        $this->lotUnknownAmount = new BooleanConstraint('partLots.instock_unknown');
        $this->lotExpirationDate = new DateTimeConstraint('partLots.expiration_date');

        $this->manufacturer = new EntityConstraint($nodesListBuilder, Manufacturer::class, 'part.manufacturer');
        $this->manufacturer_product_number = new TextConstraint('part.manufacturer_product_number');
        $this->manufacturer_product_url = new TextConstraint('part.manufacturer_product_url');
        $this->manufacturing_status = new ChoiceConstraint('part.manufacturing_status');

        $this->storelocation = new EntityConstraint($nodesListBuilder, Storelocation::class, 'partLots.storage_location');

        $this->attachmentsCount = new IntConstraint('COUNT(attachments)');
        $this->attachmentType = new EntityConstraint($nodesListBuilder, AttachmentType::class, 'attachments.attachment_type');
        $this->attachmentName = new TextConstraint('attachments.name');

        $this->orderdetailsCount = new IntConstraint('COUNT(orderdetails)');
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



}
