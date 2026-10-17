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
namespace App\Controller;

use App\DataTables\PlannedProjectsDataTable;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\PlannedProject;
use App\Entity\ProjectSystem\PlannedProjectStatus;
use App\Entity\ProjectSystem\Project;
use App\Form\ProjectSystem\BuildPlannedProjectType;
use App\Helpers\Projects\BuildPlannedProjectRequest;
use App\Services\LogSystem\EventCommentHelper;
use App\Services\ProjectSystem\ProjectPlanningHelper;
use App\Settings\BehaviorSettings\TableSettings;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/planned_project')]
class PlannedProjectController extends AbstractController
{
    /**
     * The columns of the parts list on the info page that can be sorted by.
     */
    private const SORTABLE_COLUMNS = ['name', 'storage', 'needed', 'reserved', 'amount'];

    public function __construct(
        private readonly DataTableFactory $dataTableFactory,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '', name: 'planned_project_index')]
    public function index(Request $request, TableSettings $tableSettings): Response
    {
        $this->denyAccessUnlessGranted('@planned_projects.read');

        $table = $this->dataTableFactory->createFromType(PlannedProjectsDataTable::class, [],
            ['pageLength' => $tableSettings->fullDefaultPageSize])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('planned_projects/index.html.twig', [
            'datatable' => $table,
        ]);
    }

    #[Route(path: '/missing_parts', name: 'planned_project_missing_parts', methods: ['GET'])]
    public function missingParts(Request $request, ProjectPlanningHelper $planningHelper): Response
    {
        $this->denyAccessUnlessGranted('@planned_projects.read');

        $selected_ids = array_values(array_filter(
            array_map('intval', $request->query->all('plan')),
            static fn (int $id): bool => $id > 0
        ));

        $all_planned_projects = $this->entityManager->getRepository(PlannedProject::class)->findBy(
            ['status' => PlannedProjectStatus::PLANNED],
            ['name' => 'ASC']
        );

        //Lists, per planned build, every part it can currently not be built with: not enough stock is reserved for
        //it (e.g. because there was not enough when it was planned) and not enough additional stock is available
        //to make up for it, or a lot's stock was reduced below what is reserved on it since (e.g. by a stocktake).
        //Optionally filtered to a subset of planned builds.
        $shortages = [];
        foreach ($all_planned_projects as $plannedProject) {
            if ($selected_ids !== [] && !in_array($plannedProject->getID(), $selected_ids, true)) {
                continue;
            }

            $rows = [];

            $project = $plannedProject->getProject();
            if ($project instanceof Project) {
                foreach ($project->getBomEntries() as $bomEntry) {
                    $shortfall = $planningHelper->getShortfallForBomEntry($plannedProject, $bomEntry);
                    if ($shortfall <= 0) {
                        continue;
                    }

                    $rows[] = [
                        'part' => $bomEntry->getPart(),
                        'reason' => $plannedProject->getReservedAmountForBOMEntry($bomEntry) < $plannedProject->getRequiredAmountForBOMEntry($bomEntry)
                            ? 'planned_build.missing_parts.reason.partial_reservation'
                            : 'planned_build.missing_parts.reason.lot_overcommitted',
                        'shortfall' => $shortfall,
                    ];
                }
            }

            if ($rows !== []) {
                $shortages[] = [
                    'plannedProject' => $plannedProject,
                    'rows' => $rows,
                ];
            }
        }

        return $this->render('planned_projects/missing_parts.html.twig', [
            'shortages' => $shortages,
            'all_planned_projects' => $all_planned_projects,
            'selected_ids' => $selected_ids,
        ]);
    }

    #[Route(path: '/{id}', name: 'planned_project_info', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function info(PlannedProject $plannedProject, Request $request, ProjectPlanningHelper $planningHelper): Response
    {
        $this->denyAccessUnlessGranted('read', $plannedProject);

        $sort = $request->query->getString('sort', 'name');
        if (!in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'name';
        }
        $direction = 'desc' === $request->query->getString('dir', 'asc') ? 'desc' : 'asc';

        $buildForm = null;
        $canBuild = false;
        $missingBomEntries = [];
        if (PlannedProjectStatus::PLANNED === $plannedProject->getStatus()) {
            $missingBomEntries = $planningHelper->getMissingBomEntriesForPlan($plannedProject);
            $canBuild = [] === $missingBomEntries;

            $buildForm = $this->createForm(BuildPlannedProjectType::class, new BuildPlannedProjectRequest($plannedProject));

            $cancelForm = $this->createFormBuilder()
                ->add('comment', TextareaType::class, [
                    'label' => 'planned_build.cancel.comment',
                    'required' => false,
                ])
                ->add('submit', SubmitType::class, [
                    'label' => 'planned_build.cancel.btn',
                ])
                ->getForm();
        }

        return $this->render('planned_projects/info.html.twig', [
            'planned_project' => $plannedProject,
            'build_form' => $buildForm,
            'cancel_form' => $cancelForm ?? null,
            'can_build' => $canBuild,
            'missing_bom_entries' => $missingBomEntries,
            'rows' => $planningHelper->getPartRows($plannedProject, $sort, 'asc' === $direction),
            'sort' => $sort,
            'dir' => $direction,
        ]);
    }

    #[Route(path: '/{id}/build', name: 'planned_project_build', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function build(PlannedProject $plannedProject, Request $request, ProjectPlanningHelper $planningHelper, EventCommentHelper $commentHelper): Response
    {
        $this->denyAccessUnlessGranted('edit', $plannedProject);
        $this->denyAccessUnlessGranted('@parts_stock.withdraw');

        //E.g. if it was already built or cancelled in the meantime (stale page, double submit)
        if (PlannedProjectStatus::PLANNED !== $plannedProject->getStatus()) {
            $this->addFlash('error', 'planned_build.flash.not_planned');

            return $this->redirectToRoute('planned_project_info', ['id' => $plannedProject->getID()]);
        }

        $buildRequest = new BuildPlannedProjectRequest($plannedProject);
        $form = $this->createForm(BuildPlannedProjectType::class, $buildRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            //When the user asked to add the builds to the builds part, but did not select an existing lot,
            //create a new one for them - mirroring ProjectBuildType's "create new lot" behavior.
            $project = $plannedProject->getProject();
            if ($buildRequest->getAddBuildsToBuildsPart() && !$buildRequest->getBuildsPartLot() instanceof PartLot
                && $project?->getBuildPart() instanceof Part) {
                $lot = new PartLot();
                $description = 'Build ' . date('Y-m-d H:i:s');
                if ('' !== $buildRequest->getComment()) {
                    $description .= ' (' . $buildRequest->getComment() . ')';
                }
                $lot->setDescription($description);
                $project->getBuildPart()->addPartLot($lot);
                $buildRequest->setBuildsPartLot($lot);
            }

            if ('' !== $buildRequest->getComment()) {
                $commentHelper->setMessage($buildRequest->getComment());
            }

            //Flush first, so that a newly created part lot gets a DB id before it is logged.
            $this->entityManager->flush();

            try {
                $planningHelper->doBuild($buildRequest);
                $this->entityManager->flush();

                $this->addFlash('success', 'planned_build.build.flash.success');
            } catch (\RuntimeException) {
                $this->addFlash('error', 'planned_build.build.flash.not_fully_reserved');
            }
        } else {
            $this->addFlash('error', 'planned_build.build.flash.invalid_input');
        }

        return $this->redirectToRoute('planned_project_info', ['id' => $plannedProject->getID()]);
    }

    #[Route(path: '/{id}/cancel', name: 'planned_project_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(PlannedProject $plannedProject, Request $request, ProjectPlanningHelper $planningHelper, EventCommentHelper $commentHelper): Response
    {
        $this->denyAccessUnlessGranted('edit', $plannedProject);
        $this->denyAccessUnlessGranted('@parts_stock.release');

        //E.g. if it was already built or cancelled in the meantime (stale page, double submit)
        if (PlannedProjectStatus::PLANNED !== $plannedProject->getStatus()) {
            $this->addFlash('error', 'planned_build.flash.not_planned');

            return $this->redirectToRoute('planned_project_info', ['id' => $plannedProject->getID()]);
        }

        if ($this->isCsrfTokenValid('cancel' . $plannedProject->getID(), $request->request->get('_token'))) {
            $comment = $request->request->get('comment');
            if (is_string($comment) && '' !== $comment) {
                $commentHelper->setMessage($comment);
            }

            $planningHelper->cancel($plannedProject);
            $this->entityManager->flush();
            $this->addFlash('success', 'planned_build.cancel.flash.success');
        }

        return $this->redirectToRoute('planned_project_info', ['id' => $plannedProject->getID()]);
    }

    #[Route(path: '/{id}', name: 'planned_project_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(PlannedProject $plannedProject, Request $request, ProjectPlanningHelper $planningHelper): Response
    {
        $this->denyAccessUnlessGranted('delete', $plannedProject);

        if ($this->isCsrfTokenValid('delete' . $plannedProject->getID(), $request->request->get('_token'))) {
            //A still-planned plan is cancelled (releasing its reservations) before it is removed, so removing
            //a planned project from the history list can never leave orphaned reservations behind.
            if (PlannedProjectStatus::PLANNED === $plannedProject->getStatus()) {
                $planningHelper->cancel($plannedProject);
            }

            $this->entityManager->remove($plannedProject);
            $this->entityManager->flush();
            $this->addFlash('success', 'planned_build.delete.flash.success');
        }

        return $this->redirectToRoute('planned_project_index');
    }
}
