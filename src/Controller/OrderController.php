<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\OrderSystem\Order;
use App\Entity\OrderSystem\OrderItem;
use App\Entity\Parts\PartLot;
use App\Form\OrderSystem\OrderType;
use App\Form\OrderSystem\OrderingHelperType;
use App\Form\OrderSystem\ReceiveOrderType;
use App\Repository\OrderSystem\OrderRepository;
use App\Services\OrderSystem\OrderingHelperService;
use App\Services\Parts\PartLotWithdrawAddHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/orders')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderingHelperService $orderingHelperService,
        private readonly PartLotWithdrawAddHelper $withdrawAddHelper,
    ) {
    }

    #[Route(path: '/', name: 'order_list')]
    public function list(): Response
    {
        $this->denyAccessUnlessGranted('@orders.read');

        /** @var OrderRepository $repo */
        $repo = $this->entityManager->getRepository(Order::class);
        $orders = $repo->findAllSortedByDate();

        return $this->render('orders/list.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route(path: '/new', name: 'order_new')]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('@orders.create');

        $order = new Order();
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($order);
            $this->entityManager->flush();
            $this->addFlash('success', 'order.flash.created');
            return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
        }

        return $this->render('orders/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: '/{id}', name: 'order_show', requirements: ['id' => '\d+'])]
    public function show(Order $order, Request $request): Response
    {
        $this->denyAccessUnlessGranted('read', $order);

        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted('edit', $order);
            $this->entityManager->flush();
            $this->addFlash('success', 'order.flash.saved');
            return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
        }

        return $this->render('orders/show.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route(path: '/{id}/delete', name: 'order_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Order $order, Request $request): Response
    {
        $this->denyAccessUnlessGranted('delete', $order);

        if (!$this->isCsrfTokenValid('delete_order_' . $order->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'csrf_error');
            return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
        }

        $this->entityManager->remove($order);
        $this->entityManager->flush();
        $this->addFlash('success', 'order.flash.deleted');
        return $this->redirectToRoute('order_list');
    }

    #[Route(path: '/{id}/receive', name: 'order_receive', requirements: ['id' => '\d+'])]
    public function receive(Order $order, Request $request): Response
    {
        $this->denyAccessUnlessGranted('receive', $order);

        // Build quantity map from order items
        $quantities = [];
        foreach ($order->getItems() as $item) {
            if ($item->getId() !== null) {
                $quantities[$item->getId()] = $item->getQuantity();
            }
        }

        $form = $this->createForm(ReceiveOrderType::class, null, ['quantities' => $quantities]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            foreach ($order->getItems() as $item) {
                $fieldName = 'item_' . $item->getId();
                if (!isset($formData[$fieldName])) {
                    continue;
                }
                $receivedQty = (float) $formData[$fieldName];
                if ($receivedQty <= 0) {
                    continue;
                }

                $part = $item->getPart();
                if ($part === null) {
                    continue;
                }

                // Find the first writable lot, or create one if none exists
                $targetLot = null;
                foreach ($part->getPartLots() as $lot) {
                    if ($this->withdrawAddHelper->canAdd($lot)) {
                        $targetLot = $lot;
                        break;
                    }
                }

                if ($targetLot === null) {
                    $targetLot = new PartLot();
                    $targetLot->setPart($part);
                    $targetLot->setAmount(0);
                    $this->entityManager->persist($targetLot);
                    $part->addPartLot($targetLot);
                }

                $this->withdrawAddHelper->add($targetLot, $receivedQty, 'Received from order: ' . $order->getName());
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'order.receive.flash.success');
            return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
        }

        return $this->render('orders/receive.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route(path: '/{id}/export-csv', name: 'order_export_csv', requirements: ['id' => '\d+'])]
    public function exportCsv(Order $order): Response
    {
        $this->denyAccessUnlessGranted('read', $order);

        // Group items by supplier name
        /** @var array<string, OrderItem[]> $bySupplier */
        $bySupplier = [];
        foreach ($order->getItems() as $item) {
            $supplierName = $item->getSupplier()?->getName() ?? 'Unassigned';
            $bySupplier[$supplierName][] = $item;
        }

        if (count($bySupplier) === 1) {
            // Single supplier: return CSV directly
            $supplierName = array_key_first($bySupplier);
            $items = $bySupplier[$supplierName];
            $response = new StreamedResponse(function () use ($items) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['SKU', 'Quantity', 'Part Name']);
                foreach ($items as $item) {
                    fputcsv($handle, [
                        $item->getEffectiveSupplierPartNr() ?? '',
                        $item->getQuantity(),
                        $item->getName(),
                    ]);
                }
                fclose($handle);
            });
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $order->getName() . '_' . $supplierName);
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.csv"');
            return $response;
        }

        // Multiple suppliers: ZIP archive
        $zip = new \ZipArchive();
        $tmpFile = tempnam(sys_get_temp_dir(), 'order_export_');
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($bySupplier as $supplierName => $items) {
            $csv = "SKU,Quantity,Part Name\n";
            foreach ($items as $item) {
                $sku = str_replace('"', '""', $item->getEffectiveSupplierPartNr() ?? '');
                $name = str_replace('"', '""', $item->getName());
                $csv .= '"' . $sku . '",' . $item->getQuantity() . ',"' . $name . '"' . "\n";
            }
            $safeSupplierName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $supplierName);
            $zip->addFromString($safeSupplierName . '.csv', $csv);
        }
        $zip->close();

        $content = file_get_contents($tmpFile);
        unlink($tmpFile);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/zip');
        $safeOrderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $order->getName());
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $safeOrderName . '_export.zip"');
        return $response;
    }

    #[Route(path: '/ordering-helper', name: 'order_ordering_helper')]
    public function orderingHelper(Request $request): Response
    {
        $this->denyAccessUnlessGranted('@orders.read');

        $form = $this->createForm(OrderingHelperType::class);
        $form->handleRequest($request);

        $previewItems = null;
        $previewRequests = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $projectRows = $data['projects'] ?? [];

            // Build request list, filter rows with no project selected
            $requests = [];
            foreach ($projectRows as $row) {
                if ($row['project'] !== null) {
                    $requests[] = [
                        'project' => $row['project'],
                        'build_count' => max(1, (int) ($row['build_count'] ?? 1)),
                    ];
                }
            }

            if (empty($requests)) {
                $this->addFlash('warning', 'order.ordering_helper.no_projects_selected');
                return $this->redirectToRoute('order_ordering_helper');
            }

            $previewItems = $this->orderingHelperService->computeNeededItems($requests);
            $previewRequests = $requests;

            // If "Save as Order" button was clicked
            if ($form->get('save_order')->isClicked() && !empty($previewItems)) {
                $this->denyAccessUnlessGranted('@orders.create');
                $orderName = ($data['order_name'] !== '' && $data['order_name'] !== null)
                    ? $data['order_name']
                    : $this->generateOrderName($requests);
                $order = $this->orderingHelperService->createOrderFromProjects($requests, $orderName);
                $this->entityManager->persist($order);
                $this->entityManager->flush();
                $this->addFlash('success', 'order.flash.created');
                return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
            }
        }

        return $this->render('orders/ordering_helper.html.twig', [
            'form' => $form,
            'preview_items' => $previewItems,
        ]);
    }

    /**
     * Generates a default order name based on the selected projects.
     * @param array<array{project: \App\Entity\ProjectSystem\Project, build_count: int}> $requests
     */
    private function generateOrderName(array $requests): string
    {
        $projectNames = array_map(
            fn($r) => $r['project']->getName() . ' x' . $r['build_count'],
            $requests
        );
        $date = (new \DateTimeImmutable())->format('Y-m-d');
        return 'Order ' . $date . ' — ' . implode(', ', array_slice($projectNames, 0, 3));
    }
}
