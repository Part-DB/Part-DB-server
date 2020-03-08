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

namespace App\Entity\Specifications;


use Symfony\Component\Validator\Constraints as Assert;

class Specification
{
    /**
     * @var string The name of the specification (e.g. "Collector-Base Voltage"). Required!
     * @Assert\NotBlank()
     */
    protected $name = "";

    /**
     * @var string The mathematical symbol for this specification. Can be rendered pretty later. Should be short
     * @Assert\Length(max=10)
     */
    protected $symbol = "";

    /**
     * @var float|null The guaranteed minimum value of this property.
     * @Assert\Type({"float","null"})
     * @Assert\LessThanOrEqual(propertyPath="value_typical")
     * @Assert\LessThan(propertyPath="value_max")
     */
    protected $value_min;

    /**
     * @var float|null The typical value of this property.
     * @Assert\Type({"null", "float"})
     */
    protected $value_typical;

    /**
     * @var float|null The maximum value of this property.
     * @Assert\Type({"float", "null"})
     * @Assert\GreaterThanOrEqual(propertyPath="value_typical")
     */
    protected $value_max;

    /**
     * @var string The unit in which the value values are given (e.g. V)
     * @Assert\Length(max=5)
     */
    protected $unit = "";

    /**
     * @var string A text value for the given property.
     *
     */
    protected $value_text = "";

    /**
     * Returns the name of the specification (e.g. "Collector-Base Voltage").
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the name of the specification. This value is required.
     * @param  string  $name
     * @return $this
     */
    public function setName(string $name): Specification
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Returns the mathematical symbol for this specification (e.g. "V_CB")
     * @return string
     */
    public function getSymbol(): string
    {
        return $this->symbol;
    }

    /**
     * Sets the mathematical symbol for this specification (e.g. "V_CB")
     * @param  string  $symbol
     * @return $this
     */
    public function setSymbol(string $symbol): Specification
    {
        $this->symbol = $symbol;
        return $this;
    }

    /**
     * Returns The guaranteed minimum value of this property.
     * @return float|null
     */
    public function getValueMin(): ?float
    {
        return $this->value_min;
    }

    /**
     * Sets the minimum value of this property.
     * @param  float|null  $value_min
     * @return $this
     */
    public function setValueMin(?float $value_min): Specification
    {
        $this->value_min = $value_min;
        return $this;
    }

    /**
     * Returns the typical value of this property.
     * @return float|null
     */
    public function getValueTypical(): ?float
    {
        return $this->value_typical;
    }

    /**
     * Sets the typical value of this property
     * @param  float  $value_typical
     * @return $this
     */
    public function setValueTypical(?float $value_typical): Specification
    {
        $this->value_typical = $value_typical;
        return $this;
    }

    /**
     * Returns the guaranteed maximum value
     * @return float|null
     */
    public function getValueMax(): ?float
    {
        return $this->value_max;
    }

    /**
     * Sets the guaranteed maximum value
     * @param  float|null  $value_max
     * @return $this
     */
    public function setValueMax(?float $value_max): Specification
    {
        $this->value_max = $value_max;
        return $this;
    }

    /**
     * Returns the unit used by the value (e.g. "V")
     * @return string
     */
    public function getUnit(): string
    {
        return $this->unit;
    }

    /**
     * Sets the unit used by the value.
     * @param  string  $unit
     * @return $this
     */
    public function setUnit(string $unit): Specification
    {
        $this->unit = $unit;
        return $this;
    }

    /**
     * Returns the text value.
     * @return string
     */
    public function getValueText(): string
    {
        return $this->value_text;
    }

    /**
     * Sets the text value.
     * @param  string  $value_text
     * @return $this
     */
    public function setValueText(string $value_text): Specification
    {
        $this->value_text = $value_text;
        return $this;
    }


}