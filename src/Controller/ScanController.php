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

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Controller;

use App\Exceptions\InfoProviderNotActiveException;
use App\Form\LabelSystem\ScanDialogType;
use App\Services\InfoProviderSystem\Providers\LCSCProvider;
use App\Services\LabelSystem\BarcodeScanner\BarcodeScanResultHandler;
use App\Services\LabelSystem\BarcodeScanner\BarcodeScanHelper;
use App\Services\LabelSystem\BarcodeScanner\BarcodeScanResultInterface;
use App\Services\LabelSystem\BarcodeScanner\BarcodeSourceType;
use App\Services\LabelSystem\BarcodeScanner\LocalBarcodeScanResult;
use App\Services\LabelSystem\BarcodeScanner\LCSCBarcodeScanResult;
use App\Services\LabelSystem\BarcodeScanner\EIGP114BarcodeScanResult;
use Doctrine\ORM\EntityNotFoundException;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use App\Services\InfoProviderSystem\ProviderRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Entity\Parts\Part;
use \App\Entity\Parts\StorageLocation;
use Symfony\UX\Turbo\TurboBundle;

/**
 * @see \App\Tests\Controller\ScanControllerTest
 */
#[Route(path: '/scan')]
class ScanController extends AbstractController
{
    public function __construct(
        protected BarcodeScanResultHandler $resultHandler,
        protected BarcodeScanHelper $barcodeNormalizer,
    ) {}

    #[Route(path: '', name: 'scan_dialog')]
    public function dialog(Request $request, #[MapQueryParameter] ?string $input = null): Response
    {
        $this->denyAccessUnlessGranted('@tools.label_scanner');

        $form = $this->createForm(ScanDialogType::class);
        $form->handleRequest($request);

        // If JS is working, scanning uses /scan/lookup and this action just renders the page.
        // This fallback only runs if user submits the form manually or uses ?input=...
        if ($input === null && $form->isSubmitted() && $form->isValid()) {
            $input = $form['input']->getData();
        }


        if ($input !== null && $input !== '') {
            $mode = $form->isSubmitted() ? $form['mode']->getData() : null;
            $infoMode = $form->isSubmitted() && $form['info_mode']->getData();

            try {
                $scan = $this->barcodeNormalizer->scanBarcodeContent($input, $mode ?? null);

                // If not in info mode, mimic “normal scan” behavior: redirect if possible.
                if (!$infoMode) {

                    // Try to get an Info URL if possible
                    $url = $this->resultHandler->getInfoURL($scan);
                    if ($url !== null) {
                        return $this->redirect($url);
                    }

                    //Try to get an creation URL if possible (only for vendor codes)
                    $createUrl = $this->buildCreateUrlForScanResult($scan);
                    if ($createUrl !== null) {
                        return $this->redirect($createUrl);
                    }

                    //// Otherwise: show “not found” (not “format unknown”)
                    $this->addFlash('warning', 'scan.qr_not_found');
                } else { // Info mode
                    // Info mode fallback: render page with prefilled result
                    $decoded = $scan->getDecodedForInfoMode();

                    //Try to resolve to an entity, to enhance info mode with entity-specific data
                    $dbEntity = $this->resultHandler->resolveEntity($scan);
                    $resolvedPart = $this->resultHandler->resolvePart($scan);
                    $openUrl = $this->resultHandler->getInfoURL($scan);

                    //If no entity is found, try to create an URL for creating a new part (only for vendor codes)
                    $createUrl = null;
                    if ($dbEntity === null) {
                        $createUrl = $this->buildCreateUrlForScanResult($scan);
                    }

                    if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
                        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);
                        return $this->renderBlock('label_system/scanner/scanner.html.twig', 'scan_results', [
                            'decoded' => $decoded,
                            'entity' => $dbEntity,
                            'part' => $resolvedPart,
                            'openUrl' => $openUrl,
                            'createUrl' => $createUrl,
                        ]);
                    }

                }
            } catch (\Throwable $e) {
                // Keep fallback user-friendly; avoid 500
                $this->addFlash('warning', 'scan.format_unknown');
            }
        }

        //When we reach here, only the flash messages are relevant, so if it's a Turbo request, only send the flash message fragment, so the client can show it without a full page reload
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);
            //Only send our flash message, so the client can show it without a full page reload
            return $this->renderBlock('_turbo_control.html.twig', 'flashes');
        }

        return $this->render('label_system/scanner/scanner.html.twig', [
            'form' => $form,

            //Info mode
            'decoded' => $decoded ?? null,
            'entity' => $dbEntity ?? null,
            'part' => $resolvedPart ?? null,
            'openUrl' => $openUrl ?? null,
            'createUrl' => $createUrl ?? null,
        ]);
    }

    /**
     * The route definition for this action is done in routes.yaml, as it does not use the _locale prefix as the other routes.
     */
    public function scanQRCode(string $type, int $id): Response
    {
        $type = strtolower($type);

        try {
            $this->addFlash('success', 'scan.qr_success');

            if (!isset(BarcodeScanHelper::QR_TYPE_MAP[$type])) {
                throw new InvalidArgumentException('Unknown type: '.$type);
            }
            //Construct the scan result manually, as we don't have a barcode here
            $scan_result = new LocalBarcodeScanResult(
                target_type: BarcodeScanHelper::QR_TYPE_MAP[$type],
                target_id: $id,
                //The routes are only used on the internal generated QR codes
                source_type: BarcodeSourceType::INTERNAL
            );

            return $this->redirect($this->resultHandler->getInfoURL($scan_result) ?? throw new EntityNotFoundException("Not found"));
        } catch (EntityNotFoundException) {
            $this->addFlash('success', 'scan.qr_not_found');

            return $this->redirectToRoute('homepage');
        }
    }

    /**
     * Builds a URL for creating a new part based on the barcode data, handles exceptions and shows user-friendly error messages if the provider is not active or if there is an error during URL generation.
     * @param BarcodeScanResultInterface $scanResult
     * @return string|null
     */
    private function buildCreateUrlForScanResult(BarcodeScanResultInterface $scanResult): ?string
    {
        try {
            return $this->resultHandler->getCreationURL($scanResult);
        } catch (InfoProviderNotActiveException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Throwable) {
            // Don’t break scanning UX if provider lookup fails
            $this->addFlash('error', 'An error occurred while looking up the provider for this barcode. Please try again later.');
        }

        return null;
    }
}
