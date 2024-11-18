<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Services\ImportExportSystem\PartKeeprImporter;

use App\Entity\Attachments\ProjectAttachment;
use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * This service is used to other non-mandatory data from a PartKeepr export.
 * You have to import the datastructures and parts first to use project import!
 */
class PKOptionalImporter
{
    use PKImportHelperTrait;

    public function __construct(EntityManagerInterface $em, PropertyAccessorInterface $propertyAccessor)
    {
        $this->em = $em;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * Import the projects from the given data.
     * @return int The number of imported projects
     */
    public function importProjects(array $data): int
    {
        if (!isset($data['project'])) {
            throw new \RuntimeException('$data must contain a "project" key!');
        }
        if (!isset($data['projectpart'])) {
            throw new \RuntimeException('$data must contain a "projectpart" key!');
        }

        $projects_data = $data['project'];
        $projectparts_data = $data['projectpart'];

        //First import the projects
        foreach ($projects_data as $project_data) {
            $project = new Project();
            $project->setName($project_data['name']);
            $project->setDescription($project_data['description'] ?? '');

            $this->setIDOfEntity($project, $project_data['id']);
            $this->em->persist($project);
        }
        $this->em->flush();

        //Then the project BOM entries
        foreach ($projectparts_data as $projectpart_data) {
            /** @var Project $project */
            $project = $this->em->find(Project::class, (int) $projectpart_data['project_id']);
            if (!$project) {
                throw new \RuntimeException('Could not find project with ID '.$projectpart_data['project_id']);
            }

            $bom_entry = new ProjectBOMEntry();
            $bom_entry->setQuantity((float) $projectpart_data['quantity']);
            $bom_entry->setName($projectpart_data['remarks']);
            $this->setAssociationField($bom_entry, 'part', Part::class, $projectpart_data['part_id']);

            $comments = [];
            if (!empty($projectpart_data['lotNumber'])) {
                $comments[] = 'Lot number: '.$projectpart_data['lotNumber'];
            }
            if (!empty($projectpart_data['overage'])) {
                $comments[] = 'Overage: '.$projectpart_data['overage'].($projectpart_data['overageType'] ? ' %' : ' pcs');
            }
            $bom_entry->setComment(implode(',', $comments));

            $project->addBomEntry($bom_entry);
        }
        $this->em->flush();

        $this->importAttachments($data, 'projectattachment', Project::class, 'project_id', ProjectAttachment::class);

        return is_countable($projects_data) ? count($projects_data) : 0;
    }

    /**
     * Import the users from the given data.
     * @return int The number of imported users
     */
    public function importUsers(array $data): int
    {
        if (!isset($data['fosuser'])) {
            throw new \RuntimeException('$data must contain a "fosuser" key!');
        }

        //All imported users get assigned to the "PartKeepr Users" group
        $group_users = $this->em->find(Group::class, 3);
        $group = $this->em->getRepository(Group::class)->findOneBy(['name' => 'PartKeepr Users', 'parent' => $group_users]);
        if ($group === null) {
            $group = new Group();
            $group->setName('PartKeepr Users');
            $group->setParent($group_users);
            $this->em->persist($group);
        }


        $users_data = $data['fosuser'];
        foreach ($users_data as $user_data) {
            if (in_array($user_data['username'], ['admin', 'anonymous'], true)) {
                continue;
            }

            $user = new User();
            $user->setName($user_data['username']);
            $user->setEmail($user_data['email']);
            $user->setGroup($group);

            //User is disabled by default
            $user->setDisabled(true);

            //We let doctrine generate a new ID for the user
            $this->em->persist($user);
        }

        $this->em->flush();

        return is_countable($users_data) ? count($users_data) : 0;
    }
}
