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


namespace App\Services\EDA;

use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Services\Cache\ElementCacheTagGenerator;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class KiCadHelper
{

    public function __construct(
        private readonly NodesListBuilder $nodesListBuilder,
        private readonly TagAwareCacheInterface $kicadCache,
        private readonly EntityManagerInterface $em,
        private readonly ElementCacheTagGenerator $tagGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly int $category_depth,
    ) {
        if ($this->category_depth < 0) {
            throw new \InvalidArgumentException('The category depth must be greater than or equal to 0');
        }
    }

    /**
     * Returns an array of objects containing all categories in the database in the format required by KiCAD.
     * The categories are flattened and sorted by their full path.
     * Categories, which contain no parts, are filtered out.
     * The result is cached for performance and invalidated on category changes.
     * @return array
     */
    public function getCategories(): array
    {
        return $this->kicadCache->get('kicad_categories_' . $this->category_depth, function (ItemInterface $item) {
            //Invalidate the cache on category changes
            $secure_class_name = $this->tagGenerator->getElementTypeCacheTag(Category::class);
            $item->tag($secure_class_name);

            $categories = $this->nodesListBuilder->typeToNodesList(Category::class);
            $repo = $this->em->getRepository(Category::class);
            $result = [];
            foreach ($categories as $category) {
                //Skip invisible categories
                if ($category->getEdaInfo()->getInvisible() ?? false) {
                    continue;
                }

                //Skip categories with a depth greater than the configured one
                if ($category->getLevel() > $this->category_depth) {
                    continue;
                }

                /** @var $category Category */
                //Ensure that the category contains parts
                //For the last level, we need to use a recursive query, otherwise we can use a simple query
                $parts_count = $category->getLevel() >= $this->category_depth ? $repo->getPartsCountRecursive($category) : $repo->getPartsCount($category);

                if ($parts_count < 1) {
                    continue;
                }

                //Format the category for KiCAD
                $result[] = [
                    'id' => (string)$category->getId(),
                    'name' => $category->getFullPath('/'),
                ];
            }

            return $result;
        });
    }

    /**
     * Returns an array of objects containing all parts for the given category in the format required by KiCAD.
     * The result is cached for performance and invalidated on category or part changes.
     * @param  Category  $category
     * @return array
     */
    public function getCategoryParts(Category $category): array
    {
        return $this->kicadCache->get('kicad_category_parts_'.$category->getID() . '_' . $this->category_depth,
            function (ItemInterface $item) use ($category) {
                $item->tag([
                    $this->tagGenerator->getElementTypeCacheTag(Category::class),
                    $this->tagGenerator->getElementTypeCacheTag(Part::class)
                ]);

                $category_repo = $this->em->getRepository(Category::class);
                if ($category->getLevel() >= $this->category_depth) {
                    //Get all parts for the category and its children
                    $parts = $category_repo->getPartsRecursive($category);
                } else {
                    //Get only direct parts for the category (without children), as the category is not collapsed
                    $parts = $category_repo->getParts($category);
                }

                $result = [];
                foreach ($parts as $part) {
                    //If the part is invisible, then skip it
                    if ($part->getEdaInfo()->getInvisible() ?? $part->getCategory()?->getEdaInfo()->getInvisible() ?? false) {
                        continue;
                    }

                    $result[] = [
                        'id' => (string)$part->getId(),
                        'name' => $part->getName(),
                        'description' => $part->getDescription(),
                    ];
                }

                return $result;
            });
    }

    public function getKiCADPart(Part $part): array
    {
        $result = [
            'id' => (string)$part->getId(),
            'name' => $part->getName(),
            "symbolIdStr" => $part->getEdaInfo()->getKicadSymbol() ?? $part->getCategory()?->getEdaInfo()->getKicadSymbol() ?? "",
            "exclude_from_bom" => $this->boolToKicadBool($part->getEdaInfo()->getExcludeFromBom() ?? $part->getCategory()?->getEdaInfo()->getExcludeFromBom() ?? false),
            "exclude_from_board" => $this->boolToKicadBool($part->getEdaInfo()->getExcludeFromBoard() ?? $part->getCategory()?->getEdaInfo()->getExcludeFromBoard() ?? false),
            "exclude_from_sim" => $this->boolToKicadBool($part->getEdaInfo()->getExcludeFromSim() ?? $part->getCategory()?->getEdaInfo()->getExcludeFromSim() ?? true),
            "fields" => []
        ];

        $result["fields"]["footprint"] = $this->createField($part->getEdaInfo()->getKicadFootprint() ?? $part->getFootprint()?->getEdaInfo()->getKicadFootprint() ?? "");
        $result["fields"]["reference"] = $this->createField($part->getEdaInfo()->getReferencePrefix() ?? 'U', true);
        $result["fields"]["value"] = $this->createField($part->getEdaInfo()->getValue() ?? $part->getName(), true);
        $result["fields"]["keywords"] = $this->createField($part->getTags());

        //Use the part info page as datasheet link. It must be an absolute URL.
        $result["fields"]["datasheet"] = $this->createField(
            $this->urlGenerator->generate(
                'part_info',
                ['id' => $part->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL)
        );

        //Add basic fields
        $result["fields"]["description"] = $this->createField($part->getDescription());
        if ($part->getCategory()) {
            $result["fields"]["Category"] = $this->createField($part->getCategory()->getFullPath('/'));
        }
        if ($part->getManufacturer()) {
            $result["fields"]["Manufacturer"] = $this->createField($part->getManufacturer()->getName());
        }
        if ($part->getManufacturerProductNumber() !== "") {
            $result['fields']["MPN"] = $this->createField($part->getManufacturerProductNumber());
        }
        if ($part->getManufacturingStatus()) {
            $result["fields"]["Manufacturing Status"] = $this->createField(
            //Always use the english translation
                $this->translator->trans($part->getManufacturingStatus()->toTranslationKey(), locale: 'en')
            );
        }
        if ($part->getFootprint()) {
            $result["fields"]["Part-DB Footprint"] = $this->createField($part->getFootprint()->getName());
        }
        if ($part->getPartUnit()) {
            $unit = $part->getPartUnit()->getName();
            if ($part->getPartUnit()->getUnit() !== "") {
                $unit .= ' ('.$part->getPartUnit()->getUnit().')';
            }
            $result["fields"]["Part-DB Unit"] = $this->createField($unit);
        }
        if ($part->getMass()) {
            $result["fields"]["Mass"] = $this->createField($part->getMass() . ' g');
        }
        $result["fields"]["Part-DB ID"] = $this->createField($part->getId());
        if (!empty($part->getIpn())) {
            $result["fields"]["Part-DB IPN"] = $this->createField($part->getIpn());
        }


        return $result;
    }

    /**
     * Converts a boolean value to the format required by KiCAD.
     * @param  bool  $value
     * @return string
     */
    private function boolToKicadBool(bool $value): string
    {
        return $value ? 'True' : 'False';
    }

    /**
     * Creates a field array for KiCAD
     * @param  string|int|float  $value
     * @param  bool  $visible
     * @return array
     */
    private function createField(string|int|float $value, bool $visible = false): array
    {
        return [
            'value' => (string)$value,
            'visible' => $this->boolToKicadBool($visible),
        ];
    }
}