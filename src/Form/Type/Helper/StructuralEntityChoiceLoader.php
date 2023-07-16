<?php

declare(strict_types=1);

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

use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\PriceInformations\Currency;
use App\Repository\StructuralDBElementRepository;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\ChoiceList\Loader\AbstractChoiceLoader;
use Symfony\Component\OptionsResolver\Options;

class StructuralEntityChoiceLoader extends AbstractChoiceLoader
{
    private ?string $additional_element = null;

    private ?AbstractStructuralDBElement $starting_element = null;

    public function __construct(private readonly Options $options, private readonly NodesListBuilder $builder, private readonly EntityManagerInterface $entityManager)
    {
    }

    protected function loadChoices(): iterable
    {
        //If the starting_element is set and not persisted yet, add it to the list
        if ($this->starting_element !== null && $this->starting_element->getID() === null) {
            $tmp = [$this->starting_element];
        } else {
            $tmp = [];
        }

        if ($this->additional_element) {
            $tmp = $this->createNewEntitiesFromValue($this->additional_element);
            $this->additional_element = null;
        }

        return array_merge($tmp, $this->builder->typeToNodesList($this->options['class'], null));
    }

    public function createNewEntitiesFromValue(string $value): array
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('Cannot create new entity, because the name is empty!');
        }

        //Check if the value is matching the starting value element, we use the choice_value option to get the name of the starting element
        if ($this->starting_element !== null
            && $this->starting_element->getID() === null //Element must not be persisted yet
            && $this->options['choice_value']($this->starting_element) === $value) {

            //Then reuse the starting element
            $this->entityManager->persist($this->starting_element);
            return [$this->starting_element];
        }

        if (!$this->options['allow_add']) {
            throw new \RuntimeException('Cannot create new entity, because allow_add is not enabled!');
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

    /**
     * Gets the initial value used to populate the field.
     * @return AbstractStructuralDBElement|null
     */
    public function getStartingElement(): ?AbstractStructuralDBElement
    {
        return $this->starting_element;
    }

    /**
     * Sets the initial value used to populate the field. This will always be an allowed value.
     * @param  AbstractStructuralDBElement|null  $starting_element
     * @return StructuralEntityChoiceLoader
     */
    public function setStartingElement(?AbstractStructuralDBElement $starting_element): StructuralEntityChoiceLoader
    {
        $this->starting_element = $starting_element;
        return $this;
    }


}
