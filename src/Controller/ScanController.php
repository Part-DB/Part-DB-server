<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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


use App\Services\LabelSystem\BarcodeParser;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/scan")
 * @package App\Controller
 */
class ScanController extends AbstractController
{
    protected $barcodeParser;

    public function __construct(BarcodeParser $barcodeParser)
    {
        $this->barcodeParser = $barcodeParser;
    }

    /**
     * The route definition for this action is done in routes.yaml, as it does not use the _locale prefix as the other routes
     * @param  string  $type
     * @param  int  $id
     */
    public function scanQRCode(string $type, int $id)
    {
        try {
            $this->addFlash('success', 'scan.qr_success');
            return $this->redirect($this->barcodeParser->getQRRedirectTarget($type, $id));
        } catch (EntityNotFoundException $exception) {
            $this->addFlash('success', 'scan.qr_not_found');
            return $this->redirectToRoute('homepage');
        }
    }
}