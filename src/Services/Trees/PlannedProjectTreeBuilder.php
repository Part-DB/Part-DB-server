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
namespace App\Services\Trees;

use App\Entity\ProjectSystem\PlannedProject;
use App\Entity\ProjectSystem\PlannedProjectStatus;
use App\Helpers\Trees\TreeViewNode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the "Planned Projects" node that is appended to the Projects sidebar tree. Unlike Project itself,
 * PlannedProject is a flat (non-hierarchical) list, so this hand-assembles a single group node with leaf
 * children, mirroring ToolsTreeBuilder's style rather than the generic TreeViewGenerator (which is built
 * around hierarchical AbstractStructuralDBElement classes).
 */
class PlannedProjectTreeBuilder
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Returns the "Planned Projects" tree node (as a single-element array), or an empty array if the current
     * user is not allowed to see planned projects at all.
     *
     * @return TreeViewNode[]
     */
    public function getTree(): array
    {
        if (!$this->security->isGranted('@planned_projects.read')) {
            return [];
        }

        $plannedProjects = $this->entityManager->getRepository(PlannedProject::class)->findBy(
            ['status' => PlannedProjectStatus::PLANNED],
            ['name' => 'ASC']
        );

        $children = array_map(function (PlannedProject $plannedProject): TreeViewNode {
            $node = new TreeViewNode(
                $plannedProject->getName(),
                $this->urlGenerator->generate('planned_project_info', ['id' => $plannedProject->getID()])
            );
            $node->setId($plannedProject->getID());

            return $node;
        }, $plannedProjects);

        return [
            (new TreeViewNode(
                $this->translator->trans('planned_build.labelp'),
                $this->urlGenerator->generate('planned_project_index'),
                $children
            ))->setIcon('fa-fw fa-treeview fa-solid fa-list-check'),
        ];
    }
}
