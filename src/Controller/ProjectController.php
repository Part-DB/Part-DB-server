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

namespace App\Controller;

use App\DataTables\ProjectBomEntriesDataTable;
use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Form\ProjectSystem\ProjectBOMEntryCollectionType;
use App\Form\ProjectSystem\ProjectBuildType;
use App\Form\Type\StructuralEntityType;
use App\Helpers\Projects\ProjectBuildRequest;
use App\Services\ProjectSystem\ProjectBuildHelper;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\DataTableFactory;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * @Route("/project")
 */
class ProjectController extends AbstractController
{
    private DataTableFactory $dataTableFactory;

    public function __construct(DataTableFactory $dataTableFactory)
    {
        $this->dataTableFactory = $dataTableFactory;
    }

    /**
     * @Route("/{id}/info", name="project_info", requirements={"id"="\d+"})
     */
    public function info(Project $project, Request $request, ProjectBuildHelper $buildHelper)
    {
        $this->denyAccessUnlessGranted('read', $project);

        $table = $this->dataTableFactory->createFromType(ProjectBomEntriesDataTable::class, ['project' => $project])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('projects/info/info.html.twig', [
            'buildHelper' => $buildHelper,
            'datatable' => $table,
            'project' => $project,
        ]);
    }

    /**
     * @Route("/{id}/build", name="project_build", requirements={"id"="\d+"})
     */
    public function build(Project $project, Request $request, ProjectBuildHelper $buildHelper, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('read', $project);

        //If no number of builds is given (or it is invalid), just assume 1
        $number_of_builds = $request->query->getInt('n', 1);
        if ($number_of_builds < 1) {
            $number_of_builds = 1;
        }

        $projectBuildRequest = new ProjectBuildRequest($project, $number_of_builds);
        $form = $this->createForm(ProjectBuildType::class, $projectBuildRequest);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                //Ensure that the user can withdraw stock from all parts
                $this->denyAccessUnlessGranted('@parts_stock.withdraw');

                //We have to do a flush already here, so that the newly created partLot gets an ID and can be logged to DB later.
                $entityManager->flush();
                $buildHelper->doBuild($projectBuildRequest);
                $entityManager->flush();
                $this->addFlash('success', 'project.build.flash.success');

                return $this->redirect(
                    $request->get('_redirect',
                        $this->generateUrl('project_info', ['id' => $project->getID()]
                        )));
            } else {
                $this->addFlash('error', 'project.build.flash.invalid_input');
            }
        }

        return $this->renderForm('projects/build/build.html.twig', [
            'buildHelper' => $buildHelper,
            'project' => $project,
            'build_request' => $projectBuildRequest,
            'number_of_builds' => $number_of_builds,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/add_parts", name="project_add_parts_no_id")
     * @Route("/{id}/add_parts", name="project_add_parts", requirements={"id"="\d+"})
     * @param  Request  $request
     * @param  Project|null  $project
     */
    public function addPart(Request $request, EntityManagerInterface $entityManager, ?Project $project): Response
    {
        if($project) {
            $this->denyAccessUnlessGranted('edit', $project);
        } else {
            $this->denyAccessUnlessGranted('@projects.edit');
        }

        $builder = $this->createFormBuilder();
        $builder->add('project', StructuralEntityType::class, [
            'class' => Project::class,
            'required' => true,
            'disabled' => $project !== null, //If a project is given, disable the field
            'data' => $project,
            'constraints' => [
                new NotNull()
            ]
        ]);
        $builder->add('bom_entries', ProjectBOMEntryCollectionType::class);
        $builder->add('submit', SubmitType::class, ['label' => 'save']);
        $form = $builder->getForm();

        //Preset the BOM entries with the selected parts, when the form was not submitted yet
        $preset_data = new ArrayCollection();
        foreach (explode(',', $request->get('parts', '')) as $part_id) {
            $part = $entityManager->getRepository(Part::class)->find($part_id);
            if (null !== $part) {
                //If there is already a BOM entry for this part, we use this one (we edit it then)
                $bom_entry = $entityManager->getRepository(ProjectBOMEntry::class)->findOneBy([
                    'project' => $project,
                    'part' => $part
                ]);
                if ($bom_entry) {
                    $preset_data->add($bom_entry);
                } else { //Otherwise create an empty one
                    $entry = new ProjectBOMEntry();
                    $entry->setProject($project);
                    $entry->setPart($part);
                    $preset_data->add($entry);
                }
            }
        }
        $form['bom_entries']->setData($preset_data);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $target_project = $project ?? $form->get('project')->getData();

            //Ensure that we really have acces to the selected project
            $this->denyAccessUnlessGranted('edit', $target_project);

            $data = $form->getData();
            $bom_entries = $data['bom_entries'];
            foreach ($bom_entries as $bom_entry){
                $target_project->addBOMEntry($bom_entry);
            }
            $entityManager->flush();

            //If a redirect query parameter is set, redirect to this page
            if ($request->query->get('_redirect')) {
                return $this->redirect($request->query->get('_redirect'));
            }
            //Otherwise just show the project info page
            return $this->redirectToRoute('project_info', ['id' => $target_project->getID()]);
        }

        return $this->renderForm('projects/add_parts.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }
}