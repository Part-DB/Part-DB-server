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

use App\DataTables\Filters\Constraints\DateTimeConstraint;
use App\DataTables\Filters\Constraints\EntityConstraint;
use App\DataTables\Filters\Constraints\IntConstraint;
use App\DataTables\Filters\Constraints\TextConstraint;
use App\Entity\Attachments\AttachmentType;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\QueryBuilder;

class AssemblyFilter implements FilterInterface
{

    use CompoundFilterTrait;

    public readonly IntConstraint $dbId;
    public readonly TextConstraint $ipn;
    public readonly TextConstraint $name;
    public readonly TextConstraint $description;
    public readonly TextConstraint $comment;
    public readonly DateTimeConstraint $lastModified;
    public readonly DateTimeConstraint $addedDate;

    public readonly IntConstraint $attachmentsCount;
    public readonly EntityConstraint $attachmentType;
    public readonly TextConstraint $attachmentName;

    public function __construct(NodesListBuilder $nodesListBuilder)
    {
        $this->name = new TextConstraint('assembly.name');
        $this->description = new TextConstraint('assembly.description');
        $this->comment = new TextConstraint('assembly.comment');
        $this->dbId = new IntConstraint('assembly.id');
        $this->ipn = new TextConstraint('assembly.ipn');
        $this->addedDate = new DateTimeConstraint('assembly.addedDate');
        $this->lastModified = new DateTimeConstraint('assembly.lastModified');
        $this->attachmentsCount = new IntConstraint('COUNT(_attachments)');
        $this->attachmentType = new EntityConstraint($nodesListBuilder, AttachmentType::class, '_attachments.attachment_type');
        $this->attachmentName = new TextConstraint('_attachments.name');
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $this->applyAllChildFilters($queryBuilder);
    }
}
