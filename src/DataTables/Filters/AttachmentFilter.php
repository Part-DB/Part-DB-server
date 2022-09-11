<?php

namespace App\DataTables\Filters;

use App\DataTables\Filters\Constraints\BooleanConstraint;
use App\DataTables\Filters\Constraints\DateTimeConstraint;
use App\DataTables\Filters\Constraints\EntityConstraint;
use App\DataTables\Filters\Constraints\InstanceOfConstraint;
use App\DataTables\Filters\Constraints\NumberConstraint;
use App\DataTables\Filters\Constraints\TextConstraint;
use App\Entity\Attachments\AttachmentType;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\QueryBuilder;

class AttachmentFilter implements FilterInterface
{
    use CompoundFilterTrait;

    /** @var NumberConstraint */
    protected $dbId;

    /** @var InstanceOfConstraint */
    protected $targetType;

    /** @var TextConstraint */
    protected $name;

    /** @var EntityConstraint */
    protected $attachmentType;

    /** @var BooleanConstraint */
    protected $showInTable;

    /** @var DateTimeConstraint */
    protected $lastModified;

    /** @var DateTimeConstraint */
    protected $addedDate;


    public function __construct(NodesListBuilder $nodesListBuilder)
    {
        $this->dbId = new NumberConstraint('attachment.id');
        $this->name = new TextConstraint('attachment.name');
        $this->targetType = new InstanceOfConstraint('attachment');
        $this->attachmentType = new EntityConstraint($nodesListBuilder, AttachmentType::class, 'attachment.attachment_type');
        $this->lastModified = new DateTimeConstraint('attachment.lastModified');
        $this->addedDate = new DateTimeConstraint('attachment.addedDate');
        $this->showInTable = new BooleanConstraint('attachment.show_in_table');
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $this->applyAllChildFilters($queryBuilder);
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
    public function getName(): TextConstraint
    {
        return $this->name;
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


    /**
     * @return BooleanConstraint
     */
    public function getShowInTable(): BooleanConstraint
    {
        return $this->showInTable;
    }


    /**
     * @return EntityConstraint
     */
    public function getAttachmentType(): EntityConstraint
    {
        return $this->attachmentType;
    }

    /**
     * @return InstanceOfConstraint
     */
    public function getTargetType(): InstanceOfConstraint
    {
        return $this->targetType;
    }






}