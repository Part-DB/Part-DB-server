<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Parts\Part;
use App\Form\Part\EDA\BatchEdaType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BatchEdaController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/tools/batch_eda_edit', name: 'batch_eda_edit')]
    public function batchEdaEdit(Request $request): Response
    {
        $this->denyAccessUnlessGranted('@parts.edit');

        $ids = $request->query->getString('ids', '');
        $redirectUrl = $request->query->getString('_redirect', '');

        //Parse part IDs and load parts
        $idArray = array_filter(array_map('intval', explode(',', $ids)));
        $parts = $this->entityManager->getRepository(Part::class)->findBy(['id' => $idArray]);

        if ($parts === []) {
            $this->addFlash('error', 'batch_eda.no_parts_selected');

            return $redirectUrl !== '' ? $this->redirect($redirectUrl) : $this->redirectToRoute('parts_show_all');
        }

        $form = $this->createForm(BatchEdaType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updated = 0;

            foreach ($parts as $part) {
                $this->denyAccessUnlessGranted('edit', $part);
                $edaInfo = $part->getEdaInfo();

                if ($form->get('apply_reference_prefix')->getData()) {
                    $edaInfo->setReferencePrefix($form->get('reference_prefix')->getData() ?: null);
                    $updated++;
                }
                if ($form->get('apply_value')->getData()) {
                    $edaInfo->setValue($form->get('value')->getData() ?: null);
                    $updated++;
                }
                if ($form->get('apply_kicad_symbol')->getData()) {
                    $edaInfo->setKicadSymbol($form->get('kicad_symbol')->getData() ?: null);
                    $updated++;
                }
                if ($form->get('apply_kicad_footprint')->getData()) {
                    $edaInfo->setKicadFootprint($form->get('kicad_footprint')->getData() ?: null);
                    $updated++;
                }
                if ($form->get('apply_visibility')->getData()) {
                    $edaInfo->setVisibility($form->get('visibility')->getData());
                    $updated++;
                }
                if ($form->get('apply_exclude_from_bom')->getData()) {
                    $edaInfo->setExcludeFromBom($form->get('exclude_from_bom')->getData());
                    $updated++;
                }
                if ($form->get('apply_exclude_from_board')->getData()) {
                    $edaInfo->setExcludeFromBoard($form->get('exclude_from_board')->getData());
                    $updated++;
                }
                if ($form->get('apply_exclude_from_sim')->getData()) {
                    $edaInfo->setExcludeFromSim($form->get('exclude_from_sim')->getData());
                    $updated++;
                }
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'batch_eda.success');

            return $redirectUrl !== '' ? $this->redirect($redirectUrl) : $this->redirectToRoute('parts_show_all');
        }

        return $this->render('parts/batch_eda_edit.html.twig', [
            'form' => $form->createView(),
            'parts' => $parts,
            'redirect_url' => $redirectUrl,
        ]);
    }
}
