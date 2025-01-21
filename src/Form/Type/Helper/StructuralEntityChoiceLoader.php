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

use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Repository\StructuralDBElementRepository;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\ChoiceList\Loader\AbstractChoiceLoader;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @template T of AbstractStructuralDBElement
 */
class StructuralEntityChoiceLoader extends AbstractChoiceLoader
{
    private ?string $additional_element = null;

    private ?AbstractNamedDBElement $starting_element = null;

    private ?FormInterface $form = null;

    public function __construct(
        private readonly Options $options,
        private readonly NodesListBuilder $builder,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator
    ) {
    }

    protected function loadChoices(): iterable
    {
        //If the starting_element is set and not persisted yet, add it to the list
        $tmp = $this->starting_element !== null && $this->starting_element->getID() === null ? [$this->starting_element] : [];

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
            //If we have a form, add an error to it, to improve the user experience
            if ($this->form !== null) {
                $this->form->addError(
                    new FormError($this->translator->trans('entity.select.creating_new_entities_not_allowed')
                    )
                );
            } else {
                throw new \RuntimeException('Cannot create new entity, because allow_add is not enabled!');
            }
        }


        /** @var class-string<T> $class */
        $class = $this->options['class'];

        /** @var StructuralDBElementRepository<T> $repo */
        $repo = $this->entityManager->getRepository($class);


        $entities = $repo->getNewEntityFromPath($value, '->');

        $results = [];

        foreach ($entities as $entity) {
            //If the entity is newly created (ID null), add it as result and persist it.
            if ($entity->getID() === null) {
                //Only persist the entities if it is allowed
                if ($this->options['allow_add']) {
                    $this->entityManager->persist($entity);
                }
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
     * @return AbstractNamedDBElement|null
     */
    public function getStartingElement(): ?AbstractNamedDBElement
    {
        return $this->starting_element;
    }

    /**
     * Sets the form that this loader is bound to.
     * @param  FormInterface|null  $form
     * @return void
     */
    public function setForm(?FormInterface $form): void
    {
        $this->form = $form;
    }

    /**
     * Sets the initial value used to populate the field. This will always be an allowed value.
     * @param  AbstractNamedDBElement|null  $starting_element
     * @return StructuralEntityChoiceLoader
     */
    public function setStartingElement(?AbstractNamedDBElement $starting_element): StructuralEntityChoiceLoader
    {
        $this->starting_element = $starting_element;
        return $this;
    }

    protected function doLoadChoicesForValues(array $values, ?callable $value): array
    {
        // Normalize the data (remove whitespaces around the arrow sign) and leading/trailing whitespaces
        // This is required so that the value that is generated for an new entity based on its name structure is
        // the same as the value that is generated for the same entity after it is persisted.
        // Otherwise, errors occurs that the element could not be found.
        foreach ($values as &$data) {
            $data = trim((string) $data);
            $data = preg_replace('/\s*->\s*/', '->', $data);
        }
        unset ($data);

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }


}
