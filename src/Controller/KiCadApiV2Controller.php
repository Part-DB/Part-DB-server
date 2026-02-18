<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Controller;

use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Services\EDA\KiCadHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * KiCad HTTP Library API v2 controller.
 *
 * v1 spec: https://dev-docs.kicad.org/en/apis-and-binding/http-libraries/index.html
 * v2 spec (draft): https://gitlab.com/RosyDev/kicad-dev-docs/-/blob/http-lib-v2/content/apis-and-binding/http-libraries/http-lib-v2-00.adoc
 *
 * Differences from v1:
 * - Volatile fields: Stock and Storage Location are marked volatile (shown in KiCad but NOT saved to schematic)
 * - Category descriptions: Uses actual category comments instead of URLs
 * - Response format: Arrays wrapped in objects for extensibility
 */
#[Route('/kicad-api/v2')]
class KiCadApiV2Controller extends AbstractController
{
    public function __construct(
        private readonly KiCadHelper $kiCADHelper,
    ) {
    }

    #[Route('/', name: 'kicad_api_v2_root')]
    public function root(): Response
    {
        $this->denyAccessUnlessGranted('HAS_ACCESS_PERMISSIONS');

        return $this->json([
            'categories' => '',
            'parts' => '',
        ]);
    }

    #[Route('/categories.json', name: 'kicad_api_v2_categories')]
    public function categories(Request $request): Response
    {
        $this->denyAccessUnlessGranted('@categories.read');

        $data = $this->kiCADHelper->getCategories();
        return $this->createCacheableJsonResponse($request, $data, 300);
    }

    #[Route('/parts/category/{category}.json', name: 'kicad_api_v2_category')]
    public function categoryParts(Request $request, ?Category $category): Response
    {
        if ($category !== null) {
            $this->denyAccessUnlessGranted('read', $category);
        } else {
            $this->denyAccessUnlessGranted('@categories.read');
        }
        $this->denyAccessUnlessGranted('@parts.read');

        $minimal = $request->query->getBoolean('minimal', false);
        $data = $this->kiCADHelper->getCategoryParts($category, $minimal);
        return $this->createCacheableJsonResponse($request, $data, 300);
    }

    #[Route('/parts/{part}.json', name: 'kicad_api_v2_part')]
    public function partDetails(Request $request, Part $part): Response
    {
        $this->denyAccessUnlessGranted('read', $part);

        // Use API v2 format with volatile fields
        $data = $this->kiCADHelper->getKiCADPart($part, 2);
        return $this->createCacheableJsonResponse($request, $data, 60);
    }

    private function createCacheableJsonResponse(Request $request, array $data, int $maxAge): Response
    {
        $response = new JsonResponse($data);
        $response->setEtag(md5(json_encode($data)));
        $response->headers->set('Cache-Control', 'private, max-age=' . $maxAge);
        $response->isNotModified($request);

        return $response;
    }
}
