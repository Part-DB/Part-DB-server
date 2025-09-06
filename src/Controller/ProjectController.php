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
use Psr\Log\LoggerInterface;
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
                    $request->get(
                        '_redirect',
                        $this->generateUrl(
                            'project_info',
                            ['id' => $project->getID()]
                        )
                    )
                );
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
    public function importBOM(
        Request $request,
        EntityManagerInterface $entityManager,
        Project $project,
        BOMImporter $BOMImporter,
        ValidatorInterface $validator
    ): Response {
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
                'project.bom_import.type.kicad_schematic' => 'kicad_schematic',
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

            $import_type = $form->get('type')->getData();

            try {
                // For schematic imports, redirect to field mapping step
                if ($import_type === 'kicad_schematic') {
                    // Store file content and options in session for field mapping step
                    $file_content = $form->get('file')->getData()->getContent();
                    $clear_existing = $form->get('clear_existing_bom')->getData();

                    $request->getSession()->set('bom_import_data', $file_content);
                    $request->getSession()->set('bom_import_clear', $clear_existing);

                    return $this->redirectToRoute('project_import_bom_map_fields', ['id' => $project->getID()]);
                }

                // For PCB imports, proceed directly
                $entries = $BOMImporter->importFileIntoProject($form->get('file')->getData(), $project, [
                    'type' => $import_type,
                ]);

                // Validate the project entries
                $errors = $validator->validateProperty($project, 'bom_entries');

                // If no validation errors occurred, save the changes and redirect to edit page
                if (count($errors) === 0) {
                    $this->addFlash('success', t('project.bom_import.flash.success', ['%count%' => count($entries)]));
                    $entityManager->flush();
                    return $this->redirectToRoute('project_edit', ['id' => $project->getID()]);
                }

                // When we get here, there were validation errors
                $this->addFlash('error', t('project.bom_import.flash.invalid_entries'));

            } catch (\UnexpectedValueException | SyntaxError $e) {
                $this->addFlash('error', t('project.bom_import.flash.invalid_file', ['%message%' => $e->getMessage()]));
            }
        }

        return $this->render('projects/import_bom.html.twig', [
            'project' => $project,
            'form' => $form,
            'errors' => $errors ?? null,
        ]);
    }

    #[Route(path: '/{id}/import_bom/map_fields', name: 'project_import_bom_map_fields', requirements: ['id' => '\d+'])]
    public function importBOMMapFields(
        Request $request,
        EntityManagerInterface $entityManager,
        Project $project,
        BOMImporter $BOMImporter,
        ValidatorInterface $validator,
        LoggerInterface $logger
    ): Response {
        $this->denyAccessUnlessGranted('edit', $project);

        // Get stored data from session
        $file_content = $request->getSession()->get('bom_import_data');
        $clear_existing = $request->getSession()->get('bom_import_clear', false);


        if (!$file_content) {
            $this->addFlash('error', 'project.bom_import.flash.session_expired');
            return $this->redirectToRoute('project_import_bom', ['id' => $project->getID()]);
        }

        // Detect fields and get suggestions
        $detected_fields = $BOMImporter->detectFields($file_content);
        $suggested_mapping = $BOMImporter->getSuggestedFieldMapping($detected_fields);

        // Create mapping of original field names to sanitized field names for template
        $field_name_mapping = [];
        foreach ($detected_fields as $field) {
            $sanitized_field = preg_replace('/[^a-zA-Z0-9_-]/', '_', $field);
            $field_name_mapping[$field] = $sanitized_field;
        }

        // Create form for field mapping
        $builder = $this->createFormBuilder();

        // Add delimiter selection
        $builder->add('delimiter', ChoiceType::class, [
            'label' => 'project.bom_import.delimiter',
            'required' => true,
            'data' => ',',
            'choices' => [
                'project.bom_import.delimiter.comma' => ',',
                'project.bom_import.delimiter.semicolon' => ';',
                'project.bom_import.delimiter.tab' => "\t",
            ]
        ]);

        // Get dynamic field mapping targets from BOMImporter
        $available_targets = $BOMImporter->getAvailableFieldTargets();
        $target_fields = ['project.bom_import.field_mapping.ignore' => ''];

        foreach ($available_targets as $target_key => $target_info) {
            $target_fields[$target_info['label']] = $target_key;
        }

        foreach ($detected_fields as $field) {
            // Sanitize field name for form use - replace invalid characters with underscores
            $sanitized_field = preg_replace('/[^a-zA-Z0-9_-]/', '_', $field);
            $builder->add('mapping_' . $sanitized_field, ChoiceType::class, [
                'label' => $field,
                'required' => false,
                'choices' => $target_fields,
                'data' => $suggested_mapping[$field] ?? '',
            ]);
        }

        $builder->add('submit', SubmitType::class, [
            'label' => 'project.bom_import.preview',
        ]);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Build field mapping array with priority support
            $field_mapping = [];
            $field_priorities = [];
            $delimiter = $form->get('delimiter')->getData();

            foreach ($detected_fields as $field) {
                $sanitized_field = preg_replace('/[^a-zA-Z0-9_-]/', '_', $field);
                $target = $form->get('mapping_' . $sanitized_field)->getData();
                if (!empty($target)) {
                    $field_mapping[$field] = $target;

                    // Get priority from request (default to 10)
                    $priority = $request->request->get('priority_' . $sanitized_field, 10);
                    $field_priorities[$field] = (int) $priority;
                }
            }

            // Validate field mapping
            $validation = $BOMImporter->validateFieldMapping($field_mapping, $detected_fields);

            if (!$validation['is_valid']) {
                foreach ($validation['errors'] as $error) {
                    $this->addFlash('error', $error);
                }
                foreach ($validation['warnings'] as $warning) {
                    $this->addFlash('warning', $warning);
                }

                return $this->render('projects/import_bom_map_fields.html.twig', [
                    'project' => $project,
                    'form' => $form->createView(),
                    'detected_fields' => $detected_fields,
                    'suggested_mapping' => $suggested_mapping,
                    'field_name_mapping' => $field_name_mapping,
                ]);
            }

            // Show warnings but continue
            foreach ($validation['warnings'] as $warning) {
                $this->addFlash('warning', $warning);
            }

            try {
                // Re-detect fields with chosen delimiter
                $detected_fields = $BOMImporter->detectFields($file_content, $delimiter);

                // Clear existing BOM entries if requested
                if ($clear_existing) {
                    $existing_count = $project->getBomEntries()->count();
                    $logger->info('Clearing existing BOM entries', [
                        'existing_count' => $existing_count,
                        'project_id' => $project->getID(),
                    ]);
                    $project->getBomEntries()->clear();
                    $entityManager->flush();
                    $logger->info('Existing BOM entries cleared');
                } else {
                    $existing_count = $project->getBomEntries()->count();
                    $logger->info('Keeping existing BOM entries', [
                        'existing_count' => $existing_count,
                        'project_id' => $project->getID(),
                    ]);
                }

                // Validate data before importing
                $validation_result = $BOMImporter->validateBOMData($file_content, [
                    'type' => 'kicad_schematic',
                    'field_mapping' => $field_mapping,
                    'field_priorities' => $field_priorities,
                    'delimiter' => $delimiter,
                ]);

                // Log validation results
                $logger->info('BOM import validation completed', [
                    'total_entries' => $validation_result['total_entries'],
                    'valid_entries' => $validation_result['valid_entries'],
                    'invalid_entries' => $validation_result['invalid_entries'],
                    'error_count' => count($validation_result['errors']),
                    'warning_count' => count($validation_result['warnings']),
                ]);

                // Show validation warnings to user
                foreach ($validation_result['warnings'] as $warning) {
                    $this->addFlash('warning', $warning);
                }

                // If there are validation errors, show them and stop
                 if (!empty($validation_result['errors'])) {
                    foreach ($validation_result['errors'] as $error) {
                        $this->addFlash('error', $error);
                    }

                    return $this->render('projects/import_bom_map_fields.html.twig', [
                        'project' => $project,
                        'form' => $form->createView(),
                        'detected_fields' => $detected_fields,
                        'suggested_mapping' => $suggested_mapping,
                        'field_name_mapping' => $field_name_mapping,
                        'validation_result' => $validation_result,
                    ]);
                }

                // Import with field mapping and priorities (validation already passed)
                $entries = $BOMImporter->stringToBOMEntries($file_content, [
                    'type' => 'kicad_schematic',
                    'field_mapping' => $field_mapping,
                    'field_priorities' => $field_priorities,
                    'delimiter' => $delimiter,
                ]);

                // Log entry details for debugging
                $logger->info('BOM entries created', [
                    'total_entries' => count($entries),
                ]);

                foreach ($entries as $index => $entry) {
                    $logger->debug("BOM entry {$index}", [
                        'name' => $entry->getName(),
                        'mountnames' => $entry->getMountnames(),
                        'quantity' => $entry->getQuantity(),
                        'comment' => $entry->getComment(),
                        'part_id' => $entry->getPart()?->getID(),
                    ]);
                }

                // Assign entries to project
                $logger->info('Adding BOM entries to project', [
                    'entries_count' => count($entries),
                    'project_id' => $project->getID(),
                ]);

                foreach ($entries as $index => $entry) {
                    $logger->debug("Adding BOM entry {$index} to project", [
                        'name' => $entry->getName(),
                        'part_id' => $entry->getPart()?->getID(),
                        'quantity' => $entry->getQuantity(),
                    ]);
                    $project->addBomEntry($entry);
                }

                // Validate the project entries (includes collection constraints)
                $errors = $validator->validateProperty($project, 'bom_entries');

                // If no validation errors occurred, save and redirect
                if (count($errors) === 0) {
                    $this->addFlash('success', t('project.bom_import.flash.success', ['%count%' => count($entries)]));
                    $entityManager->flush();

                    // Clear session data
                    $request->getSession()->remove('bom_import_data');
                    $request->getSession()->remove('bom_import_clear');

                    return $this->redirectToRoute('project_edit', ['id' => $project->getID()]);
                }

                // When we get here, there were validation errors
                $this->addFlash('error', t('project.bom_import.flash.invalid_entries'));

                //Print validation errors to log for debugging
                foreach ($errors as $error) {
                    $logger->error('BOM entry validation error', [
                        'message' => $error->getMessage(),
                        'invalid_value' => $error->getInvalidValue(),
                    ]);
                    //And show as flash message
                    $this->addFlash('error', $error->getMessage(),);
                }

            } catch (\UnexpectedValueException | SyntaxError $e) {
                $this->addFlash('error', t('project.bom_import.flash.invalid_file', ['%message%' => $e->getMessage()]));
            }
        }

        return $this->render('projects/import_bom_map_fields.html.twig', [
            'project' => $project,
            'form' => $form,
            'detected_fields' => $detected_fields,
            'suggested_mapping' => $suggested_mapping,
            'field_name_mapping' => $field_name_mapping,
        ]);
    }

    #[Route(path: '/add_parts', name: 'project_add_parts_no_id')]
    #[Route(path: '/{id}/add_parts', name: 'project_add_parts', requirements: ['id' => '\d+'])]
    public function addPart(Request $request, EntityManagerInterface $entityManager, ?Project $project): Response
    {
        if ($project instanceof Project) {
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
            foreach ($bom_entries as $bom_entry) {
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
