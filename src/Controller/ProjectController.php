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
namespace App\Controller;

use App\DataTables\ProjectBomEntriesDataTable;
use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Form\ProjectSystem\ProjectAddPartsType;
use App\Form\ProjectSystem\ProjectBuildType;
use App\Helpers\Projects\ProjectBuildRequest;
use App\Services\ImportExportSystem\BOMImporter;
use App\Services\ProjectSystem\ProjectBuildHelper;
use App\Settings\BehaviorSettings\TableSettings;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\SyntaxError;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function Symfony\Component\Translation\t;

#[Route(path: '/project')]
class ProjectController extends AbstractController
{
    public function __construct(private readonly DataTableFactory $dataTableFactory)
    {
    }

    #[Route(path: '/{id}/info', name: 'project_info', requirements: ['id' => '\d+'])]
    public function info(Project $project, Request $request, ProjectBuildHelper $buildHelper, TableSettings $tableSettings): Response
    {
        $this->denyAccessUnlessGranted('read', $project);

        $table = $this->dataTableFactory->createFromType(ProjectBomEntriesDataTable::class, ['project' => $project],
            ['pageLength' => $tableSettings->fullDefaultPageSize])
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

    #[Route(path: '/{id}/build', name: 'project_build', requirements: ['id' => '\d+'])]
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
            }

            $this->addFlash('error', 'project.build.flash.invalid_input');
        }

        return $this->render('projects/build/build.html.twig', [
            'buildHelper' => $buildHelper,
            'project' => $project,
            'build_request' => $projectBuildRequest,
            'number_of_builds' => $number_of_builds,
            'form' => $form,
        ]);
    }

    #[Route(path: '/{id}/import_bom', name: 'project_import_bom', requirements: ['id' => '\d+'])]
    public function importBOM(Request $request, EntityManagerInterface $entityManager, Project $project,
        BOMImporter $BOMImporter, ValidatorInterface $validator): Response
    {
        $this->denyAccessUnlessGranted('edit', $project);

        $builder = $this->createFormBuilder();
        $builder->add('file', FileType::class, [
            'label' => 'import.file',
            'required' => true,
            'attr' => [
                'accept' => '.csv'
            ]
        ]);
        $builder->add('type', ChoiceType::class, [
            'label' => 'project.bom_import.type',
            'required' => true,
            'choices' => [
                'project.bom_import.type.kicad_pcbnew' => 'kicad_pcbnew',
            ]
        ]);
        $builder->add('clear_existing_bom', CheckboxType::class, [
            'label' => 'project.bom_import.clear_existing_bom',
            'required' => false,
            'data' => false,
            'help' => 'project.bom_import.clear_existing_bom.help',
        ]);
        $builder->add('submit', SubmitType::class, [
            'label' => 'import.btn',
        ]);

        $form = $builder->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            //Clear existing BOM entries if requested
            if ($form->get('clear_existing_bom')->getData()) {
                $project->getBomEntries()->clear();
                $entityManager->flush();
            }

            try {
                $entries = $BOMImporter->importFileIntoProject($form->get('file')->getData(), $project, [
                    'type' => $form->get('type')->getData(),
                ]);

                //Validate the project entries
                $errors = $validator->validateProperty($project, 'bom_entries');

                //If no validation errors occured, save the changes and redirect to edit page
                if (count ($errors) === 0) {
                    $this->addFlash('success', t('project.bom_import.flash.success', ['%count%' => count($entries)]));
                    $entityManager->flush();
                    return $this->redirectToRoute('project_edit', ['id' => $project->getID()]);
                }

                //When we get here, there were validation errors
                $this->addFlash('error', t('project.bom_import.flash.invalid_entries'));

            } catch (\UnexpectedValueException|SyntaxError $e) {
                $this->addFlash('error', t('project.bom_import.flash.invalid_file', ['%message%' => $e->getMessage()]));
            }
        }

        return $this->render('projects/import_bom.html.twig', [
            'project' => $project,
            'form' => $form,
            'errors' => $errors ?? null,
        ]);
    }

    #[Route(path: '/add_parts', name: 'project_add_parts_no_id')]
    #[Route(path: '/{id}/add_parts', name: 'project_add_parts', requirements: ['id' => '\d+'])]
    public function addPart(Request $request, EntityManagerInterface $entityManager, ?Project $project): Response
    {
        if($project instanceof Project) {
            $this->denyAccessUnlessGranted('edit', $project);
        } else {
            $this->denyAccessUnlessGranted('@projects.edit');
        }

        $form = $this->createForm(ProjectAddPartsType::class, null, [
            'project' => $project,
        ]);


        //Preset the BOM entries with the selected parts, when the form was not submitted yet
        $preset_data = new ArrayCollection();
        foreach (explode(',', (string) $request->get('parts', '')) as $part_id) {
            //Skip empty part IDs. Postgres seems to be especially sensitive to empty strings, as it does not allow them in integer columns
            if ($part_id === '') {
                continue;
            }

            $part = $entityManager->getRepository(Part::class)->find($part_id);
            if (null !== $part) {
                //If there is already a BOM entry for this part, we use this one (we edit it then)
                $bom_entry = $entityManager->getRepository(ProjectBOMEntry::class)->findOneBy([
                    'project' => $project,
                    'part' => $part
                ]);
                if ($bom_entry !== null) {
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

        return $this->render('projects/add_parts.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }
}
