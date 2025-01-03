<?php

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

use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\ProjectSystem\Project;
use App\Form\Type\Helper\StructuralEntityChoiceHelper;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This endpoint is used by the select2 library to dynamically load data (used in the multiselect action helper in parts lists)
 */
#[Route(path: '/select_api')]
class SelectAPIController extends AbstractController
{
    public function __construct(private readonly NodesListBuilder $nodesListBuilder, private readonly TranslatorInterface $translator, private readonly StructuralEntityChoiceHelper $choiceHelper)
    {
    }

    #[Route(path: '/category', name: 'select_category')]
    public function category(): Response
    {
        return $this->getResponseForClass(Category::class);
    }

    #[Route(path: '/footprint', name: 'select_footprint')]
    public function footprint(): Response
    {
        return $this->getResponseForClass(Footprint::class, true);
    }

    #[Route(path: '/manufacturer', name: 'select_manufacturer')]
    public function manufacturer(): Response
    {
        return $this->getResponseForClass(Manufacturer::class, true);
    }

    #[Route(path: '/measurement_unit', name: 'select_measurement_unit')]
    public function measurement_unit(): Response
    {
        return $this->getResponseForClass(MeasurementUnit::class, true);
    }

    #[Route(path: '/project', name: 'select_project')]
    public function projects(): Response
    {
        return $this->getResponseForClass(Project::class, false);
    }

    #[Route(path: '/export_level', name: 'select_export_level')]
    public function exportLevel(): Response
    {
        $entries = [
            1 => $this->translator->trans('export.level.simple'),
            2 => $this->translator->trans('export.level.extended'),
            3 => $this->translator->trans('export.level.full'),
        ];

        return $this->json(array_map(static fn($key, $value) => [
            'text' => $value,
            'value' => $key,
        ], array_keys($entries), $entries));
    }

    #[Route(path: '/label_profiles', name: 'select_label_profiles')]
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

    #[Route(path: '/label_profiles_lot', name: 'select_label_profiles_lot')]
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
            'value' => "",
        ]);

        return $arr;
    }

    protected function buildJSONStructure(array $nodes_list): array
    {
        $entries = [];

        foreach ($nodes_list as $node) {
            if ($node instanceof AbstractStructuralDBElement) {
                $entry = [
                    'text' => $this->choiceHelper->generateChoiceLabel($node),
                    'value' => $this->choiceHelper->generateChoiceValue($node),
                ];

                $data = $this->choiceHelper->generateChoiceAttr($node, [
                    'disable_not_selectable' => true,
                ]);
                //Remove the data-* prefix for each key
                $data = array_combine(
                    array_map(static function ($key) {
                        if (str_starts_with($key, 'data-')) {
                            return substr($key, 5);
                        }
                        return $key;
                    }, array_keys($data)),
                    $data
                );

                //Append the data to the entry
                $entry += $data;

                /*$entry = [
                    'text' => str_repeat('&nbsp;&nbsp;&nbsp;', $node->getLevel()).htmlspecialchars($node->getName()),
                    'value' => $node->getID(),
                    'data-subtext' => $node->getParent() ? $node->getParent()->getFullPath() : null,
                ];*/
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
