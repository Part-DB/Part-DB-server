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


use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parts\Part;
use App\Helpers\LabelResponse;
use App\Services\LabelSystem\LabelGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/label")
 * @package App\Controller
 */
class LabelController extends AbstractController
{
    protected $labelGenerator;

    public function __construct(LabelGenerator $labelGenerator)
    {
        $this->labelGenerator = $labelGenerator;
    }

    /**
     * @Route("/{profile}/{part}/view")
     */
    public function view(LabelProfile $profile, Part $part)
    {
        $label = $this->labelGenerator->generateLabel($profile->getOptions(), $part);

        $response = new LabelResponse($label);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'label.pdf');

        return $response;
    }
}