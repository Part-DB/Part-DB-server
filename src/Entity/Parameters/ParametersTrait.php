<?php

declare(strict_types=1);

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

namespace App\Entity\Parameters;

use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

trait ParametersTrait
{
    /**
     * Mapping done in subclasses.
     *
     * @var AbstractParameter[]|Collection
     * @Assert\Valid()
     */
    protected $parameters;

    /**
     * Return all associated specifications.
     *
     * @return AbstractParameter[]|Collection
     */
    public function getParameters(): Collection
    {
        return $this->parameters;
    }

    /**
     * Add a new parameter information.
     *
     * @return $this
     */
    public function addParameter(AbstractParameter $parameter): self
    {
        $parameter->setElement($this);
        $this->parameters->add($parameter);

        return $this;
    }

    public function removeParameter(AbstractParameter $parameter): self
    {
        $this->parameters->removeElement($parameter);

        return $this;
    }

    public function getGroupedParameters(): array
    {
        $tmp = [];

        foreach ($this->parameters as $parameter) {
            $tmp[$parameter->getGroup()][] = $parameter;
        }
        return $tmp;
    }
}
