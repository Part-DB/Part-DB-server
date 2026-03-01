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

    /**
     * Compute shared EDA values across all parts. If all parts have the same value for a field, return it.
     * @param Part[] $parts
     * @return array<string, mixed>
     */
    private function getSharedEdaValues(array $parts): array
    {
        $fields = [
            'reference_prefix' => static fn (Part $p) => $p->getEdaInfo()->getReferencePrefix(),
            'value' => static fn (Part $p) => $p->getEdaInfo()->getValue(),
            'kicad_symbol' => static fn (Part $p) => $p->getEdaInfo()->getKicadSymbol(),
            'kicad_footprint' => static fn (Part $p) => $p->getEdaInfo()->getKicadFootprint(),
            'visibility' => static fn (Part $p) => $p->getEdaInfo()->getVisibility(),
            'exclude_from_bom' => static fn (Part $p) => $p->getEdaInfo()->getExcludeFromBom(),
            'exclude_from_board' => static fn (Part $p) => $p->getEdaInfo()->getExcludeFromBoard(),
            'exclude_from_sim' => static fn (Part $p) => $p->getEdaInfo()->getExcludeFromSim(),
        ];

        $data = [];
        foreach ($fields as $key => $getter) {
            $values = array_map($getter, $parts);
            $unique = array_unique($values, SORT_REGULAR);
            if (count($unique) === 1) {
                $data[$key] = $unique[array_key_first($unique)];
            }
        }

        return $data;
    }

    #[Route('/tools/batch_eda_edit', name: 'batch_eda_edit')]
    public function batchEdaEdit(Request $request): Response
    {
        $this->denyAccessUnlessGranted('@parts.edit');

        $ids = $request->query->getString('ids', '');
        $redirectUrl = $request->query->getString('_redirect', '');

        //Parse part IDs and load parts
        $idArray = array_filter(array_map(intval(...), explode(',', $ids)), static fn (int $id): bool => $id > 0);
        $parts = $this->entityManager->getRepository(Part::class)->findBy(['id' => $idArray]);

        if ($parts === []) {
            $this->addFlash('error', 'batch_eda.no_parts_selected');

            return $redirectUrl !== '' ? $this->redirect($redirectUrl) : $this->redirectToRoute('parts_show_all');
        }

        //Pre-populate form with shared values (when all parts have the same value)
        $initialData = $this->getSharedEdaValues($parts);
        $form = $this->createForm(BatchEdaType::class, $initialData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($parts as $part) {
                $this->denyAccessUnlessGranted('edit', $part);
                $edaInfo = $part->getEdaInfo();

                if ($form->get('apply_reference_prefix')->getData()) {
                    $edaInfo->setReferencePrefix($form->get('reference_prefix')->getData() ?: null);
                }
                if ($form->get('apply_value')->getData()) {
                    $edaInfo->setValue($form->get('value')->getData() ?: null);
                }
                if ($form->get('apply_kicad_symbol')->getData()) {
                    $edaInfo->setKicadSymbol($form->get('kicad_symbol')->getData() ?: null);
                }
                if ($form->get('apply_kicad_footprint')->getData()) {
                    $edaInfo->setKicadFootprint($form->get('kicad_footprint')->getData() ?: null);
                }
                if ($form->get('apply_visibility')->getData()) {
                    $edaInfo->setVisibility($form->get('visibility')->getData());
                }
                if ($form->get('apply_exclude_from_bom')->getData()) {
                    $edaInfo->setExcludeFromBom($form->get('exclude_from_bom')->getData());
                }
                if ($form->get('apply_exclude_from_board')->getData()) {
                    $edaInfo->setExcludeFromBoard($form->get('exclude_from_board')->getData());
                }
                if ($form->get('apply_exclude_from_sim')->getData()) {
                    $edaInfo->setExcludeFromSim($form->get('exclude_from_sim')->getData());
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
