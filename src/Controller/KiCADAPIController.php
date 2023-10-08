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
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/kicad-api/v1')]
class KiCADAPIController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {

    }

    #[Route('/', name: 'kicad_api_root')]
    public function root(): Response
    {
        //The API documentation says this can be either blank or the URL to the endpoints
        return $this->json([
            'categories' => '',
            'parts' => '',
        ]);
    }

    #[Route('/categories.json', name: 'kicad_api_categories')]
    public function categories(NodesListBuilder $nodesListBuilder): Response
    {
        $categories = $nodesListBuilder->typeToNodesList(Category::class);
        $result = [];
        foreach ($categories as $category) {
            $result[] = [
                'id' => (string) $category->getId(),
                'name' => $category->getFullPath('/'),
            ];
        }

        return $this->json($result);
    }

    #[Route('/parts/category/{category}.json', name: 'kicad_api_category')]
    public function categoryParts(Category $category): Response
    {
        $category_repo = $this->em->getRepository(Category::class);
        $parts = $category_repo->getParts($category);

        $result = [];
        foreach ($parts as $part) {
            $result[] = [
                'id' => (string) $part->getId(),
                'name' => $part->getName(),
                'description' => $part->getDescription(),
            ];
        }

        return $this->json($result);
    }

    #[Route('/parts/{part}.json', name: 'kicad_api_part')]
    public function partDetails(Part $part): Response
    {
        return $this->json($this->partToKiCADPart($part));
    }

    private function partToKiCADPart(Part $part): array
    {
        $result = [
            'id' => (string) $part->getId(),
            'name' => $part->getName(),
            "symbolIdStr" => "Device:R",
            "exclude_from_bom" => "False",
            "exclude_from_board" => "False",
            "exclude_from_sim" => "True",
            "fields" => []
        ];

        //Add misc fields
        $result["fields"]["description"] = $this->createValue($part->getDescription());
        $result["fields"]["value"] = $this->createValue($part->getName(), true);
        $result["fields"]["keywords"] = $this->createValue($part->getTags());
        if ($part->getManufacturer()) {
            $result["fields"]["manufacturer"] = $this->createValue($part->getManufacturer()->getName());
        }

        return $result;
    }

    private function createValue(string|int|float $value, bool $visible = false): array
    {
        return [
            'value' => (string) $value,
            'visible' => $visible ? 'True' : 'False',
        ];
    }
}