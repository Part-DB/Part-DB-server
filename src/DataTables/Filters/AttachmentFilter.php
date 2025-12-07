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

use App\DataTables\Filters\Constraints\AbstractConstraint;
use App\DataTables\Filters\Constraints\BooleanConstraint;
use App\DataTables\Filters\Constraints\DateTimeConstraint;
use App\DataTables\Filters\Constraints\EntityConstraint;
use App\DataTables\Filters\Constraints\InstanceOfConstraint;
use App\DataTables\Filters\Constraints\IntConstraint;
use App\DataTables\Filters\Constraints\NumberConstraint;
use App\DataTables\Filters\Constraints\TextConstraint;
use App\Entity\Attachments\AttachmentType;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Filter\AbstractFilter;

class AttachmentFilter implements FilterInterface
{
    use CompoundFilterTrait;

    public readonly NumberConstraint $dbId;
    public readonly InstanceOfConstraint $targetType;
    public readonly TextConstraint $name;
    public readonly EntityConstraint $attachmentType;
    public readonly BooleanConstraint $showInTable;
    public readonly DateTimeConstraint $lastModified;
    public readonly DateTimeConstraint $addedDate;

    public readonly TextConstraint $originalFileName;
    public readonly TextConstraint $externalLink;


    public function __construct(NodesListBuilder $nodesListBuilder)
    {
        //Must be done for every new set of attachment filters, to ensure deterministic parameter names.
        AbstractConstraint::resetParameterCounter();

        $this->dbId = new IntConstraint('attachment.id');
        $this->name = new TextConstraint('attachment.name');
        $this->targetType = new InstanceOfConstraint('attachment');
        $this->attachmentType = new EntityConstraint($nodesListBuilder, AttachmentType::class, 'attachment.attachment_type');
        $this->lastModified = new DateTimeConstraint('attachment.lastModified');
        $this->addedDate = new DateTimeConstraint('attachment.addedDate');
        $this->showInTable = new BooleanConstraint('attachment.show_in_table');
        $this->originalFileName = new TextConstraint('attachment.original_filename');
        $this->externalLink = new TextConstraint('attachment.external_path');

    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $this->applyAllChildFilters($queryBuilder);
    }
}
