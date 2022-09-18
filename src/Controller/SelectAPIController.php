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

use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Services\Trees\NodesListBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/select_api")
 */
class SelectAPIController extends AbstractController
{
    private NodesListBuilder $nodesListBuilder;
    private TranslatorInterface $translator;

    public function __construct(NodesListBuilder $nodesListBuilder, TranslatorInterface $translator)
    {
        $this->nodesListBuilder = $nodesListBuilder;
        $this->translator = $translator;
    }

    /**
     * @Route("/category", name="select_category")
     */
    public function category(): Response
    {
        return $this->getResponseForClass(Category::class);
    }

    /**
     * @Route("/footprint", name="select_footprint")
     */
    public function footprint(): Response
    {
        return $this->getResponseForClass(Footprint::class, true);
    }

    /**
     * @Route("/manufacturer", name="select_manufacturer")
     */
    public function manufacturer(): Response
    {
        return $this->getResponseForClass(Manufacturer::class, true);
    }

    /**
     * @Route("/measurement_unit", name="select_measurement_unit")
     */
    public function measurement_unit(): Response
    {
        return $this->getResponseForClass(MeasurementUnit::class, true);
    }

    protected function getResponseForClass(string $class, bool $include_empty = false): Response
    {
        $test_obj = new $class();
        $this->denyAccessUnlessGranted('read', $test_obj);

        $nodes = $this->nodesListBuilder->typeToNodesList($class);

        $json = $this->buildJSONStructure($nodes);

        if ($include_empty) {
            array_unshift($json, [
                'text' => '',
                'value' => null,
                'data-subtext' => $this->translator->trans('part_list.action.select_null'),
            ]);
        }

        return $this->json($json);
    }

    protected function buildJSONStructure(array $nodes_list): array
    {
        $entries = [];

        foreach ($nodes_list as $node) {
            /** @var AbstractStructuralDBElement $node */
            $entry = [
                'text' => str_repeat('&nbsp;&nbsp;&nbsp;', $node->getLevel()).htmlspecialchars($node->getName()),
                'value' => $node->getID(),
                'data-subtext' => $node->getParent() ? $node->getParent()->getFullPath() : null,
            ];

            $entries[] = $entry;
        }

        return $entries;
    }
}
