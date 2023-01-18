<?php
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

use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\ProjectSystem\Project;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/select_api")
 *
 * This endpoint is used by the select2 library to dynamically load data (used in the multiselect action helper in parts lists)
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

    /**
     * @Route("/project", name="select_project")
     */
    public function projects(): Response
    {
        return $this->getResponseForClass(Project::class, false);
    }

    /**
     * @Route("/label_profiles", name="select_label_profiles")
     * @return Response
     */
    public function labelProfiles(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('@labels.create_labels');

        if ($this->isGranted('@labels.read_profiles')) {
            $profiles = $entityManager->getRepository(LabelProfile::class)->getPartLabelProfiles();
            $nodes = $this->buildJSONStructure($profiles);
        } else {
            $nodes = [];
        }

        //Add the empty option
        $this->addEmptyNode($nodes, 'part_list.action.generate_label.empty');

        return $this->json($nodes);
    }

    /**
     * @Route("/label_profiles_lot", name="select_label_profiles_lot")
     * @return Response
     */
    public function labelProfilesLot(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('@labels.create_labels');

        if ($this->isGranted('@labels.read_profiles')) {
            $profiles = $entityManager->getRepository(LabelProfile::class)->getPartLotsLabelProfiles();
            $nodes = $this->buildJSONStructure($profiles);
        } else {
            $nodes = [];
        }

        //Add the empty option
        $this->addEmptyNode($nodes, 'part_list.action.generate_label.empty');

        return $this->json($nodes);
    }

    protected function getResponseForClass(string $class, bool $include_empty = false): Response
    {
        $test_obj = new $class();
        $this->denyAccessUnlessGranted('read', $test_obj);

        $nodes = $this->nodesListBuilder->typeToNodesList($class);

        $json = $this->buildJSONStructure($nodes);

        if ($include_empty) {
            $this->addEmptyNode($json);
        }

        return $this->json($json);
    }

    protected function addEmptyNode(array &$arr, string $text = 'part_list.action.select_null'): array
    {
        array_unshift($arr, [
            'text' => $this->translator->trans($text),
            'value' => null,
        ]);

        return $arr;
    }

    protected function buildJSONStructure(array $nodes_list): array
    {
        $entries = [];

        foreach ($nodes_list as $node) {
            if ($node instanceof AbstractStructuralDBElement) {
                $entry = [
                    'text' => str_repeat('&nbsp;&nbsp;&nbsp;', $node->getLevel()).htmlspecialchars($node->getName()),
                    'value' => $node->getID(),
                    'data-subtext' => $node->getParent() ? $node->getParent()->getFullPath() : null,
                ];
            } elseif ($node instanceof AbstractNamedDBElement) {
                $entry = [
                    'text' => htmlspecialchars($node->getName()),
                    'value' => $node->getID(),
                ];
            } else {
                throw new \InvalidArgumentException('Invalid node type!');
            }

            $entries[] = $entry;
        }

        return $entries;
    }
}
