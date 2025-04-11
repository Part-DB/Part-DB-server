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

use App\DataTables\AssemblyBomEntriesDataTable;
use App\Entity\AssemblySystem\Assembly;
use App\Entity\AssemblySystem\AssemblyBOMEntry;
use App\Entity\Parts\Part;
use App\Form\AssemblySystem\AssemblyAddPartsType;
use App\Form\AssemblySystem\AssemblyBuildType;
use App\Helpers\Assemblies\AssemblyBuildRequest;
use App\Services\ImportExportSystem\BOMImporter;
use App\Services\AssemblySystem\AssemblyBuildHelper;
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

use Symfony\Contracts\Translation\TranslatorInterface;
use function Symfony\Component\Translation\t;

#[Route(path: '/assembly')]
class AssemblyController extends AbstractController
{
    public function __construct(
        private readonly DataTableFactory $dataTableFactory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/{id}/info', name: 'assembly_info', requirements: ['id' => '\d+'])]
    public function info(Assembly $assembly, Request $request, AssemblyBuildHelper $buildHelper): Response
    {
        $this->denyAccessUnlessGranted('read', $assembly);

        $table = $this->dataTableFactory->createFromType(AssemblyBomEntriesDataTable::class, ['assembly' => $assembly])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('assemblies/info/info.html.twig', [
            'buildHelper' => $buildHelper,
            'datatable' => $table,
            'assembly' => $assembly,
        ]);
    }

    #[Route(path: '/{id}/build', name: 'assembly_build', requirements: ['id' => '\d+'])]
    public function build(Assembly $assembly, Request $request, AssemblyBuildHelper $buildHelper, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('read', $assembly);

        //If no number of builds is given (or it is invalid), just assume 1
        $number_of_builds = $request->query->getInt('n', 1);
        if ($number_of_builds < 1) {
            $number_of_builds = 1;
        }

        $assemblyBuildRequest = new AssemblyBuildRequest($assembly, $number_of_builds);
        $form = $this->createForm(AssemblyBuildType::class, $assemblyBuildRequest);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                //Ensure that the user can withdraw stock from all parts
                $this->denyAccessUnlessGranted('@parts_stock.withdraw');

                //We have to do a flush already here, so that the newly created partLot gets an ID and can be logged to DB later.
                $entityManager->flush();
                $buildHelper->doBuild($assemblyBuildRequest);
                $entityManager->flush();
                $this->addFlash('success', 'assembly.build.flash.success');

                return $this->redirect(
                    $request->get('_redirect',
                        $this->generateUrl('assembly_info', ['id' => $assembly->getID()]
                        )));
            }

            $this->addFlash('error', 'assembly.build.flash.invalid_input');
        }

        return $this->render('assemblies/build/build.html.twig', [
            'buildHelper' => $buildHelper,
            'assembly' => $assembly,
            'build_request' => $assemblyBuildRequest,
            'number_of_builds' => $number_of_builds,
            'form' => $form,
        ]);
    }

    #[Route(path: '/{id}/import_bom', name: 'assembly_import_bom', requirements: ['id' => '\d+'])]
    public function importBOM(Request $request, EntityManagerInterface $entityManager, Assembly $assembly,
                              BOMImporter $BOMImporter, ValidatorInterface $validator): Response
    {
        $this->denyAccessUnlessGranted('edit', $assembly);

        $builder = $this->createFormBuilder();
        $builder->add('file', FileType::class, [
            'label' => 'import.file',
            'required' => true,
            'attr' => [
                'accept' => '.csv, .json'
            ]
        ]);
        $builder->add('type', ChoiceType::class, [
            'label' => 'assembly.bom_import.type',
            'required' => true,
            'choices' => [
                'assembly.bom_import.type.json' => 'json',
                'assembly.bom_import.type.kicad_pcbnew' => 'kicad_pcbnew',
            ]
        ]);
        $builder->add('clear_existing_bom', CheckboxType::class, [
            'label' => 'assembly.bom_import.clear_existing_bom',
            'required' => false,
            'data' => false,
            'help' => 'assembly.bom_import.clear_existing_bom.help',
        ]);
        $builder->add('submit', SubmitType::class, [
            'label' => 'import.btn',
        ]);

        $form = $builder->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Clear existing entries if requested
            if ($form->get('clear_existing_bom')->getData()) {
                $assembly->getBomEntries()->clear();
                $entityManager->flush();
            }

            try {
                $importerResult = $BOMImporter->importFileIntoAssembly($form->get('file')->getData(), $assembly, [
                    'type' => $form->get('type')->getData(),
                ]);

                //Validate the assembly entries
                $errors = $validator->validateProperty($assembly, 'bom_entries');

                //If no validation errors occured, save the changes and redirect to edit page
                if (count ($errors) === 0 && $importerResult->getViolations()->count() === 0) {
                    $entries = $importerResult->getBomEntries();

                    $this->addFlash('success', t('assembly.bom_import.flash.success', ['%count%' => count($entries)]));
                    $entityManager->flush();

                    return $this->redirectToRoute('assembly_edit', ['id' => $assembly->getID()]);
                }

                //Show validation errors
                $this->addFlash('error', t('assembly.bom_import.flash.invalid_entries'));
            } catch (\UnexpectedValueException|\RuntimeException|SyntaxError $e) {
                $this->addFlash('error', t('assembly.bom_import.flash.invalid_file', ['%message%' => $e->getMessage()]));
            }
        }

        $jsonTemplate = [
            [
                "quantity" => 1.0,
                "name" => $this->translator->trans('assembly.bom_import.template.entry.name'),
                "part" => [
                    "id" => null,
                    "ipn" => $this->translator->trans('assembly.bom_import.template.entry.part.ipn'),
                    "mpnr" => $this->translator->trans('assembly.bom_import.template.entry.part.mpnr'),
                    "name" => $this->translator->trans('assembly.bom_import.template.entry.part.name'),
                    "description" => null,
                    "manufacturer" => [
                        "id" => null,
                        "name" => $this->translator->trans('assembly.bom_import.template.entry.part.manufacturer.name')
                    ],
                    "category" => [
                        "id" => null,
                        "name" => $this->translator->trans('assembly.bom_import.template.entry.part.category.name')
                    ]
                ]
            ]
        ];

        return $this->render('assemblies/import_bom.html.twig', [
            'assembly' => $assembly,
            'jsonTemplate' => $jsonTemplate,
            'form' => $form,
            'validationErrors' => $errors ?? null,
            'importerErrors' => isset($importerResult) ? $importerResult->getViolations() : null,
        ]);
    }

    #[Route(path: '/add_parts', name: 'assembly_add_parts_no_id')]
    #[Route(path: '/{id}/add_parts', name: 'assembly_add_parts', requirements: ['id' => '\d+'])]
    public function addPart(Request $request, EntityManagerInterface $entityManager, ?Assembly $assembly): Response
    {
        if($assembly instanceof Assembly) {
            $this->denyAccessUnlessGranted('edit', $assembly);
        } else {
            $this->denyAccessUnlessGranted('@assemblies.edit');
        }

        $form = $this->createForm(AssemblyAddPartsType::class, null, [
            'assembly' => $assembly,
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
                $bom_entry = $entityManager->getRepository(AssemblyBOMEntry::class)->findOneBy([
                    'assembly' => $assembly,
                    'part' => $part
                ]);
                if ($bom_entry !== null) {
                    $preset_data->add($bom_entry);
                } else { //Otherwise create an empty one
                    $entry = new AssemblyBOMEntry();
                    $entry->setAssembly($assembly);
                    $entry->setPart($part);
                    $preset_data->add($entry);
                }
            }
        }
        $form['bom_entries']->setData($preset_data);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $target_assembly = $assembly ?? $form->get('assembly')->getData();

            //Ensure that we really have acces to the selected assembly
            $this->denyAccessUnlessGranted('edit', $target_assembly);

            $data = $form->getData();
            $bom_entries = $data['bom_entries'];
            foreach ($bom_entries as $bom_entry){
                $target_assembly->addBOMEntry($bom_entry);
            }

            $entityManager->flush();

            //If a redirect query parameter is set, redirect to this page
            if ($request->query->get('_redirect')) {
                return $this->redirect($request->query->get('_redirect'));
            }
            //Otherwise just show the assembly info page
            return $this->redirectToRoute('assembly_info', ['id' => $target_assembly->getID()]);
        }

        return $this->render('assemblies/add_parts.html.twig', [
            'assembly' => $assembly,
            'form' => $form,
        ]);
    }
}
