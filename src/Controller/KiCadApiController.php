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
 * @see \App\Tests\Controller\KiCadApiControllerTest
 */
#[Route('/kicad-api/v1')]
class KiCadApiController extends AbstractController
{
    public function __construct(
        private readonly KiCadHelper $kiCADHelper,
    )
    {
    }

    #[Route('/', name: 'kicad_api_root')]
    public function root(): Response
    {
        $this->denyAccessUnlessGranted('HAS_ACCESS_PERMISSIONS');

        //The API documentation says this can be either blank or the URL to the endpoints
        return $this->json([
            'categories' => '',
            'parts' => '',
        ]);
    }

    #[Route('/categories.json', name: 'kicad_api_categories')]
    public function categories(Request $request): Response
    {
        $this->denyAccessUnlessGranted('@categories.read');

        $data = $this->kiCADHelper->getCategories();
        return $this->createCachedJsonResponse($request, $data, 300);
    }

    #[Route('/parts/category/{category}.json', name: 'kicad_api_category')]
    public function categoryParts(Request $request, ?Category $category): Response
    {
        if ($category !== null) {
            $this->denyAccessUnlessGranted('read', $category);
        } else {
            $this->denyAccessUnlessGranted('@categories.read');
        }
        $this->denyAccessUnlessGranted('@parts.read');

        $data = $this->kiCADHelper->getCategoryParts($category);
        return $this->createCachedJsonResponse($request, $data, 300);
    }

    #[Route('/parts/{part}.json', name: 'kicad_api_part')]
    public function partDetails(Request $request, Part $part): Response
    {
        $this->denyAccessUnlessGranted('read', $part);

        $data = $this->kiCADHelper->getKiCADPart($part);
        return $this->createCachedJsonResponse($request, $data, 60);
    }

    /**
     * Creates a JSON response with HTTP cache headers (ETag and Cache-Control).
     * Returns 304 Not Modified if the client's ETag matches.
     */
    private function createCachedJsonResponse(Request $request, array $data, int $maxAge): Response
    {
        $response = new JsonResponse($data);
        $response->setEtag(md5(json_encode($data)));
        $response->headers->set('Cache-Control', 'private, max-age=' . $maxAge);
        $response->isNotModified($request);

        return $response;
    }
}