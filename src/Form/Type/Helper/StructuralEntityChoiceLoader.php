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

namespace App\Form\Type\Helper;

use App\Repository\StructuralDBElementRepository;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\ChoiceList\Loader\AbstractChoiceLoader;
use Symfony\Component\OptionsResolver\Options;

class StructuralEntityChoiceLoader extends AbstractChoiceLoader
{
    private Options $options;
    private NodesListBuilder $builder;
    private EntityManagerInterface $entityManager;

    private ?string $additional_element = null;

    public function __construct(Options $options, NodesListBuilder $builder, EntityManagerInterface $entityManager)
    {
        $this->options = $options;
        $this->builder = $builder;
        $this->entityManager = $entityManager;
    }

    protected function loadChoices(): iterable
    {
        $tmp = [];
        if ($this->additional_element) {
            $tmp = $this->createNewEntitiesFromValue($this->additional_element);
            $this->additional_element = null;
        }

        return array_merge($tmp, $this->builder->typeToNodesList($this->options['class'], null));
    }

    public function createNewEntitiesFromValue(string $value): array
    {
        if (!$this->options['allow_add']) {
            throw new \RuntimeException('Cannot create new entity, because allow_add is not enabled!');
        }

        if (trim($value) === '') {
            throw new \InvalidArgumentException('Cannot create new entity, because the name is empty!');
        }

        $class = $this->options['class'];
        /** @var StructuralDBElementRepository $repo */
        $repo = $this->entityManager->getRepository($class);

        $entities = $repo->getNewEntityFromPath($value, '->');

        $results = [];

        foreach($entities as $entity) {
            //If the entity is newly created (ID null), add it as result and persist it.
            if ($entity->getID() === null) {
                $this->entityManager->persist($entity);
                $results[] = $entity;
            }
        }

        return $results;
    }

    public function setAdditionalElement(?string $element): void
    {
        $this->additional_element = $element;
    }

    public function getAdditionalElement(): ?string
    {
        return $this->additional_element;
    }

}