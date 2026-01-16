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
use App\Services\LabelSystem\BarcodeScanner\BarcodeRedirector;
use App\Services\LabelSystem\BarcodeScanner\BarcodeScanHelper;
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

        $mode = null;
        if ($input === null && $form->isSubmitted() && $form->isValid()) {
            $input = $form['input']->getData();
            $mode = $form['mode']->getData();
        }

        $infoModeData = null;
        $createUrl = null;

        if ($input !== null) {
            try {
                $scan_result = $this->barcodeNormalizer->scanBarcodeContent($input, $mode ?? null);

                //Perform a redirect if the info mode is not enabled
                if (!$form['info_mode']->getData()) {
                    try {
                        // redirect user to part page
                        return $this->redirect($this->barcodeParser->getRedirectURL($scan_result));
                    } catch (EntityNotFoundException) {
                        // Fallback: show decoded info like info-mode as part does not exist
                        $infoModeData = $scan_result->getDecodedForInfoMode();

                        $locale = $request->getLocale();

	                    // If it's an LCSC scan, offer "create part" link
        	            if ($scan_result instanceof LCSCBarcodeScanResult) {
	                        $lcscCode = $scan_result->getPC();

        	                if (is_string($lcscCode) && $lcscCode !== '') {
                	            // Prefer generating a relative URL; browser will use current host
                        	    $createUrl = "/{$locale}/part/from_info_provider/lcsc/{$lcscCode}/create";
	                        }
        	            }

			            // If EIGP114 (Mouser / Digi-Key), offer "create part" link
			            if ($scan_result instanceof EIGP114BarcodeScanResult) {
				            // Use guessed vendor and supplierPartNumber.
				            $vendor = $scan_result->guessBarcodeVendor();

                            if ($vendor === 'mouser' && is_string($scan_result->supplierPartNumber)
                                && $scan_result->supplierPartNumber !== '') {

                                try {
                                    $mouserProvider = $this->providerRegistry->getProviderByKey('mouser');

                                    if (!$mouserProvider->isActive()) {
                                        $this->addFlash('warning', 'Mouser provider is disabled / not configured.');
                                    } else {
                                        // Search Mouser using the MPN
                                        $dtos = $this->infoRetriever->searchByKeyword(
                                            keyword: $scan_result->supplierPartNumber,
                                            providers: [$mouserProvider]
                                        );

                                        // If there are results, provider_id is MouserPartNumber (per MouserProvider.php)
                                        $best = $dtos[0] ?? null;

                                        if ($best !== null && is_string($best->provider_id) && $best->provider_id !== '') {
                                            $createUrl = '/'
                                                . rawurlencode($locale)
                                                . '/part/from_info_provider/mouser/'
                                                . rawurlencode($best->provider_id)
                                                . '/create';
                                        } else {
                                            $this->addFlash('warning', 'No Mouser match found for this MPN.');
                                        }
                                    }
                                } catch (\InvalidArgumentException $e) {
                                    // provider key not found in registry
                                    $this->addFlash('warning', 'Mouser provider is not installed/enabled.');
                                } catch (\Throwable $e) {
                                    // Don’t break scanning UX if provider lookup fails
                                    $this->addFlash('warning', 'Mouser lookup failed: ' . $e->getMessage());
                                }
                            }

                            // Digikey can keep using customerPartNumber if present (it is in their barcode)
                            if ($vendor === 'digikey') {

                                try {
                                    $provider = $this->providerRegistry->getProviderByKey('digikey');

                                    if (!$provider->isActive()) {
                                        $this->addFlash('warning', 'Digi-Key provider is disabled / not configured (API key missing).');
                                    } else {
                                        $id = $scan_result->customerPartNumber ?: $scan_result->supplierPartNumber;

                                        if (is_string($id) && $id !== '') {
                                            $createUrl = '/'
                                                . rawurlencode($locale)
                                                . '/part/from_info_provider/digikey/'
                                                . rawurlencode($id)
                                                . '/create';
                                        }
                                    }
                                } catch (\InvalidArgumentException $e) {
                                    $this->addFlash('warning', 'Digi-Key provider is not installed/enabled');
                                }
                            }
                        }

                        if ($createUrl === null) {
                            $this->addFlash('warning', 'scan.qr_not_found');
                        }
                    }
                } else { //Otherwise retrieve infoModeData
                    $infoModeData = $scan_result->getDecodedForInfoMode();
                }
            } catch (InvalidArgumentException) {
                $this->addFlash('error', 'scan.format_unknown');
            }
        }

        return $this->render('label_system/scanner/scanner.html.twig', [
            'form' => $form,
            'infoModeData' => $infoModeData,
            'createUrl' => $createUrl,
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
}
