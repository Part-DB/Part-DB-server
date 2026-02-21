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

use App\Form\LabelSystem\ScanDialogType;
use App\Services\InfoProviderSystem\Providers\LCSCProvider;
use App\Services\LabelSystem\BarcodeScanner\BarcodeRedirector;
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

/**
 * @see \App\Tests\Controller\ScanControllerTest
 */
#[Route(path: '/scan')]
class ScanController extends AbstractController
{
    public function __construct(
	protected BarcodeRedirector $barcodeParser,
	protected BarcodeScanHelper $barcodeNormalizer,
        private readonly ProviderRegistry $providerRegistry,
        private readonly PartInfoRetriever $infoRetriever,
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

        $infoModeData = null;

        if ($input !== null && $input !== '') {
            $mode = $form->isSubmitted() ? $form['mode']->getData() : null;
            $infoMode = $form->isSubmitted() ? (bool) $form['info_mode']->getData() : false;

            try {
                $scan = $this->barcodeNormalizer->scanBarcodeContent((string) $input, $mode ?? null);

                // If not in info mode, mimic “normal scan” behavior: redirect if possible.
                if (!$infoMode) {
                    try {
                        $url = $this->barcodeParser->getRedirectURL($scan);
                        return $this->redirect($url);
                    } catch (EntityNotFoundException) {
                        // Decoded OK, but no part is found. If it’s a vendor code, redirect to create.
                        $createUrl = $this->buildCreateUrlForScanResult($scan);
                        if ($createUrl !== null) {
                            return $this->redirect($createUrl);
                        }

                        // Otherwise: show “not found” (not “format unknown”)
                        $this->addFlash('warning', 'scan.qr_not_found');
                    }
                }

                // Info mode fallback: render page with prefilled result
                $infoModeData = $scan->getDecodedForInfoMode();

            } catch (\Throwable $e) {
                // Keep fallback user-friendly; avoid 500
                $this->addFlash('warning', 'scan.format_unknown');
            }
        }

        return $this->render('label_system/scanner/scanner.html.twig', [
            'form' => $form,
            'infoModeData' => $infoModeData,
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

            return $this->redirect($this->barcodeParser->getRedirectURL($scan_result));
        } catch (EntityNotFoundException) {
            $this->addFlash('success', 'scan.qr_not_found');

            return $this->redirectToRoute('homepage');
        }
    }

    /**
     * Builds a URL for creating a new part based on the barcode data
     * @param BarcodeScanResultInterface $scanResult
     * @return string|null
     */
    private function buildCreateUrlForScanResult(BarcodeScanResultInterface $scanResult): ?string
    {
        // LCSC
        if ($scanResult instanceof LCSCBarcodeScanResult) {
            $lcscCode = $scanResult->lcscCode;
            if ($lcscCode !== null && $lcscCode !== '') {
                return $this->generateUrl('info_providers_create_part', [
                    'providerKey' => 'lcsc',
                    'providerId' => $lcscCode,
                ]);
            }
        }

        // Mouser / Digi-Key (EIGP114)
        if ($scanResult instanceof EIGP114BarcodeScanResult) {
            $vendor = $scanResult->guessBarcodeVendor();

            // Mouser: use supplierPartNumber -> search provider -> provider_id
            if ($vendor === 'mouser'
                && $scanResult->supplierPartNumber !== null
                && $scanResult->supplierPartNumber !== ''
            ) {
                try {
                    $mouserProvider = $this->providerRegistry->getProviderByKey('mouser');

                    if (!$mouserProvider->isActive()) {
                        $this->addFlash('warning', 'Mouser provider is disabled / not configured.');
                        return null;
                    }
                    // Search Mouser using the MPN
                    $dtos = $this->infoRetriever->searchByKeyword(
                        keyword: $scanResult->supplierPartNumber,
                        providers: [$mouserProvider]
                    );

                    // If there are results, provider_id is MouserPartNumber (per MouserProvider.php)
                    $best = $dtos[0] ?? null;

                    if ($best !== null && $best->provider_id !== '') {

                        return $this->generateUrl('info_providers_create_part', [
                            'providerKey' => 'mouser',
                            'providerId' => $best->provider_id,
                        ]);
                    }

                    $this->addFlash('warning', 'No Mouser match found for this MPN.');
                    return null;
                } catch (\InvalidArgumentException) {
                    // provider key not found in registry
                    $this->addFlash('warning', 'Mouser provider is not installed/enabled.');
                    return null;
                } catch (\Throwable $e) {
                    // Don’t break scanning UX if provider lookup fails
                    $this->addFlash('warning', 'Mouser lookup failed: ' . $e->getMessage());
                    return null;
                }
            }

            // Digi-Key: can use customerPartNumber or supplierPartNumber directly
            if ($vendor === 'digikey') {
                try {
                    $provider = $this->providerRegistry->getProviderByKey('digikey');

                    if (!$provider->isActive()) {
                        $this->addFlash('warning', 'Digi-Key provider is disabled / not configured (API key missing).');
                        return null;
                    }

                    $id = $scanResult->customerPartNumber ?: $scanResult->supplierPartNumber;

                    if (is_string($id) && $id !== '') {
                        return $this->generateUrl('info_providers_create_part', [
                            'providerKey' => 'digikey',
                            'providerId' => $id,
                        ]);
                    }
                } catch (\InvalidArgumentException) {
                    $this->addFlash('warning', 'Digi-Key provider is not installed/enabled');
                    return null;
                }
            }
        }

        return null;
    }

    private function buildLocationsForPart(Part $part): array
    {
        $byLocationId = [];

        foreach ($part->getPartLots() as $lot) {
            $loc = $lot->getStorageLocation();
            if ($loc === null) {
                continue;
            }

            $locId = $loc->getID();
            $qty = $lot->getAmount();

            if (!isset($byLocationId[$locId])) {
                $byLocationId[$locId] = [
                    'breadcrumb' => $this->buildStorageBreadcrumb($loc),
                    'qty' => $qty,
                ];
            } else {
                $byLocationId[$locId]['qty'] += $qty;
            }
        }

        return array_values($byLocationId);
    }

    private function buildStorageBreadcrumb(StorageLocation $loc): array
    {
        $items = [];
        $cur = $loc;

        // 20 is the overflow limit in src/Entity/Base/AbstractStructuralDBElement.php line ~273
        for ($i = 0; $i < 20 && $cur !== null; $i++) {
            $items[] = [
                'name' => $cur->getName(),
                'url'  => $this->generateUrl('part_list_store_location', ['id' => $cur->getID()]),
            ];

            $parent = $cur->getParent(); // inherited from AbstractStructuralDBElement
            $cur = ($parent instanceof StorageLocation) ? $parent : null;
        }

        return array_reverse($items);
    }

    /**
     * Provides XHR endpoint for looking up barcode information and return JSON response
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/lookup', name: 'scan_lookup', methods: ['POST'])]
    public function lookup(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('@tools.label_scanner');

        $input = trim($request->request->getString('input', ''));

        // We cannot use getEnum here, because we get an empty string for mode, when auto mode is selected
        $mode  = $request->request->getString('mode', BarcodeSourceType::class, '');
        if ($mode === '') {
            $modeEnum = null;
        } else {
            $modeEnum = BarcodeSourceType::from($mode); // validate enum value; will throw if invalid
        }

        $infoMode = $request->request->getBoolean('info_mode', false);

        if ($input === '') {
            return new JsonResponse(['ok' => false], 200);
        }

        try {
            $scan = $this->barcodeNormalizer->scanBarcodeContent($input, $modeEnum);
        } catch (InvalidArgumentException) {
            // Camera sometimes produces garbage decodes for a frame; ignore those.
            return new JsonResponse(['ok' => false], 200);
        }

        $decoded = $scan->getDecodedForInfoMode();

        // Determine if this barcode resolves to *anything* (part, lot->part, storelocation)
        $redirectUrl = null;
        $targetFound = false;

        try {
            $redirectUrl = $this->barcodeParser->getRedirectURL($scan);
            $targetFound = true;
        } catch (EntityNotFoundException) {
        }

        // Only resolve Part for part-like targets. Storelocation scans should remain null here.
        $part = null;
        $partName = null;
        $partUrl = null;
        $locations = [];

        if ($targetFound) {
            $part = $this->barcodeParser->resolvePartOrNull($scan);

            if ($part instanceof Part) {
                $partName = $part->getName();
                $partUrl = $this->generateUrl('app_part_show', ['id' => $part->getID()]);
                $locations = $this->buildLocationsForPart($part);
            }
        }

        // Create link only when NOT found (vendor codes)
        $createUrl = null;
        if (!$targetFound) {
            $createUrl = $this->buildCreateUrlForScanResult($scan);
        }

        // Render fragment (use openUrl for universal "Open" link)
        $html = $this->renderView('label_system/scanner/augmented_result.html.twig', [
            'decoded' => $decoded,
            'found' => $targetFound,
            'openUrl' => $redirectUrl,
            'partName' => $partName,
            'partUrl' => $partUrl,
            'locations' => $locations,
            'createUrl' => $createUrl,
        ]);

        return new JsonResponse([
            'ok' => true,
            'found' => $targetFound,
            'redirectUrl' => $redirectUrl, // client redirects only when infoMode=false
            'createUrl' => $createUrl,
            'html' => $html,
            'infoMode' => $infoMode,
        ], 200);
    }
}
