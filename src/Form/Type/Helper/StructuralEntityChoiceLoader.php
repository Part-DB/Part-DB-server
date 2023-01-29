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

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\ChoiceList\Loader\AbstractChoiceLoader;
use Symfony\Component\OptionsResolver\Options;

class StructuralEntityChoiceLoader extends AbstractChoiceLoader
{
    private Options $options;
    private NodesListBuilder $builder;
    private EntityManagerInterface $entityManager;

    public function __construct(Options $options, NodesListBuilder $builder, EntityManagerInterface $entityManager)
    {
        $this->options = $options;
        $this->builder = $builder;
        $this->entityManager = $entityManager;
    }

    protected function loadChoices(): iterable
    {
        return $this->builder->typeToNodesList($this->options['class'], null);
    }

    public function loadChoicesForValues(array $values, callable $value = null)
    {
        $tmp = parent::loadChoicesForValues($values, $value);

        if ($this->options['allow_add'] && empty($tmp)) {
            if (count($values) > 1) {
                throw new \InvalidArgumentException('Cannot add multiple entities at once.');
            }

            //Dont create a new entity for the empty option
            if ($values[0] === "" || $values[0] === null) {
                return $tmp;
            }

            return [$this->createNewEntityFromValue((string)$values[0])];
        }

        return $tmp;
    }

    /*public function loadValuesForChoices(array $choices, callable $value = null)
    {
        $tmp = parent::loadValuesForChoices($choices, $value);

        if ($this->options['allow_add'] && count($tmp) === 1) {
            if ($tmp[0] instanceof AbstractDBElement && $tmp[0]->getID() === null) {
                return [$tmp[0]->getName()];
            }

            return [(string)$choices[0]->getID()];
        }

        return $tmp;
    }*/


    public function createNewEntityFromValue(string $value): AbstractStructuralDBElement
    {
        if (!$this->options['allow_add']) {
            throw new \RuntimeException('Cannot create new entity, because allow_add is not enabled!');
        }

        if (trim($value) === '') {
            throw new \InvalidArgumentException('Cannot create new entity, because the name is empty!');
        }

        $class = $this->options['class'];
        /** @var AbstractStructuralDBElement $entity */
        $entity = new $class();
        $entity->setName($value);

        //Persist element to database
        $this->entityManager->persist($entity);

        return $entity;
    }
}