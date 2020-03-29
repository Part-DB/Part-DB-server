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

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractNamedDBElement;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity()
 * @ORM\Table("parameters")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="smallint")
 * @ORM\DiscriminatorMap({
 *      0 = "CategoryParameter",
 *      1 = "CurrencyParameter",
 *      2 = "DeviceParameter",
 *      3 = "FootprintParameter",
 *      4 = "GroupParameter",
 *      5 = "ManufacturerParameter",
 *      6 = "MeasurementUnitParameter",
 *      7 = "PartParameter",
 *      8 = "StorelocationParameter",
 *      9 = "SupplierParameter",
 *      10 = "AttachmentTypeParameter"
 * })
 */
abstract class AbstractParameter extends AbstractNamedDBElement
{
    /**
     * @var string The class of the element that can be passed to this attachment. Must be overridden in subclasses.
     */
    public const ALLOWED_ELEMENT_CLASS = '';

    /**
     * @var string The mathematical symbol for this specification. Can be rendered pretty later. Should be short
     * @Assert\Length(max=20)
     * @ORM\Column(type="string", nullable=false)
     */
    protected $symbol = '';

    /**
     * @var float|null The guaranteed minimum value of this property.
     * @Assert\Type({"float","null"})
     * @Assert\LessThanOrEqual(propertyPath="value_typical", message="parameters.validator.min_lesser_typical")
     * @Assert\LessThan(propertyPath="value_max", message="parameters.validator.min_lesser_max")
     * @ORM\Column(type="float", nullable=true)
     */
    protected $value_min;

    /**
     * @var float|null The typical value of this property.
     * @Assert\Type({"null", "float"})
     * @ORM\Column(type="float", nullable=true)
     */
    protected $value_typical;

    /**
     * @var float|null The maximum value of this property.
     * @Assert\Type({"float", "null"})
     * @Assert\GreaterThanOrEqual(propertyPath="value_typical", message="parameters.validator.max_greater_typical")
     * @ORM\Column(type="float", nullable=true)
     */
    protected $value_max;

    /**
     * @var string The unit in which the value values are given (e.g. V)
     * @Assert\Length(max=5)
     * @ORM\Column(type="string", nullable=false)
     */
    protected $unit = '';

    /**
     * @var string A text value for the given property.
     * @ORM\Column(type="string", nullable=false)
     */
    protected $value_text = '';

    /**
     * @var string The group this parameter belongs to.
     * @ORM\Column(type="string", nullable=false, name="param_group")
     */
    protected $group = '';

    /**
     * Mapping is done in sub classes.
     *
     * @var AbstractDBElement|null The element to which this parameter belongs to.
     */
    protected $element;

    public function __construct()
    {
        if ('' === static::ALLOWED_ELEMENT_CLASS) {
            throw new LogicException('An *Attachment class must override the ALLOWED_ELEMENT_CLASS const!');
        }
    }

    /**
     * Returns the name of the specification (e.g. "Collector-Base Voltage").
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the element this parameter belongs to.
     *
     * @return AbstractDBElement|null
     */
    public function getElement(): ?AbstractDBElement
    {
        return $this->element;
    }

    /**
     * Return a formatted string version of the values of the string.
     * Based on the set values it can return something like this: 34 V (12 V ... 50 V) [Text].
     *
     * @return string
     */
    public function getFormattedValue(): string
    {
        //If we just only have text value, return early
        if (null === $this->value_typical && null === $this->value_min && null === $this->value_max) {
            return $this->value_text;
        }

        $str = '';
        $bracket_opened = false;
        if ($this->value_typical) {
            $str .= $this->getValueTypicalWithUnit();
            if ($this->value_min || $this->value_max) {
                $bracket_opened = true;
                $str .= ' (';
            }
        }

        if ($this->value_max && $this->value_min) {
            $str .= $this->getValueMinWithUnit().' ... '.$this->getValueMaxWithUnit();
        } elseif ($this->value_max) {
            $str .= 'max. '.$this->getValueMaxWithUnit();
        } elseif ($this->value_min) {
            $str .= 'min. '.$this->getValueMinWithUnit();
        }

        //Add closing bracket
        if ($bracket_opened) {
            $str .= ')';
        }

        if ($this->value_text) {
            $str .= ' ['.$this->value_text.']';
        }

        return $str;
    }

    /**
     * Sets the element to which this parameter belongs to.
     *
     * @return $this
     */
    public function setElement(AbstractDBElement $element): self
    {
        if (! is_a($element, static::ALLOWED_ELEMENT_CLASS)) {
            throw new InvalidArgumentException(sprintf('The element associated with a %s must be a %s!', static::class, static::ALLOWED_ELEMENT_CLASS));
        }

        $this->element = $element;

        return $this;
    }

    /**
     * Sets the name of the specification. This value is required.
     *
     * @return $this
     */
    public function setName(string $name): AbstractNamedDBElement
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Returns the name of the group this parameter is associated to (e.g. Technical Parameters).
     *
     * @return string
     */
    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * Sets the name of the group this parameter is associated to.
     *
     * @return $this
     */
    public function setGroup(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Returns the mathematical symbol for this specification (e.g. "V_CB").
     *
     * @return string
     */
    public function getSymbol(): string
    {
        return $this->symbol;
    }

    /**
     * Sets the mathematical symbol for this specification (e.g. "V_CB").
     *
     * @return $this
     */
    public function setSymbol(string $symbol): self
    {
        $this->symbol = $symbol;

        return $this;
    }

    /**
     * Returns The guaranteed minimum value of this property.
     *
     * @return float|null
     */
    public function getValueMin(): ?float
    {
        return $this->value_min;
    }

    /**
     * Sets the minimum value of this property.
     *
     * @return $this
     */
    public function setValueMin(?float $value_min): self
    {
        $this->value_min = $value_min;

        return $this;
    }

    /**
     * Returns the typical value of this property.
     *
     * @return float|null
     */
    public function getValueTypical(): ?float
    {
        return $this->value_typical;
    }

    /**
     * Return a formatted version with the minimum value with the unit of this parameter.
     *
     * @return string
     */
    public function getValueTypicalWithUnit(): string
    {
        return $this->formatWithUnit($this->value_typical);
    }

    /**
     * Return a formatted version with the maximum value with the unit of this parameter.
     *
     * @return string
     */
    public function getValueMaxWithUnit(): string
    {
        return $this->formatWithUnit($this->value_max);
    }

    /**
     * Return a formatted version with the typical value with the unit of this parameter.
     *
     * @return string
     */
    public function getValueMinWithUnit(): string
    {
        return $this->formatWithUnit($this->value_min);
    }

    /**
     * Sets the typical value of this property.
     *
     * @param float $value_typical
     *
     * @return $this
     */
    public function setValueTypical(?float $value_typical): self
    {
        $this->value_typical = $value_typical;

        return $this;
    }

    /**
     * Returns the guaranteed maximum value.
     *
     * @return float|null
     */
    public function getValueMax(): ?float
    {
        return $this->value_max;
    }

    /**
     * Sets the guaranteed maximum value.
     *
     * @return $this
     */
    public function setValueMax(?float $value_max): self
    {
        $this->value_max = $value_max;

        return $this;
    }

    /**
     * Returns the unit used by the value (e.g. "V").
     *
     * @return string
     */
    public function getUnit(): string
    {
        return $this->unit;
    }

    /**
     * Sets the unit used by the value.
     *
     * @return $this
     */
    public function setUnit(string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    /**
     * Returns the text value.
     *
     * @return string
     */
    public function getValueText(): string
    {
        return $this->value_text;
    }

    /**
     * Sets the text value.
     *
     * @return $this
     */
    public function setValueText(string $value_text): self
    {
        $this->value_text = $value_text;

        return $this;
    }

    public function getIDString(): string
    {
        return 'PM'.sprintf('%09d', $this->getID());
    }

    /**
     * Return a string representation and (if possible) with its unit.
     *
     * @return string
     */
    protected function formatWithUnit(float $value, string $format = '%g'): string
    {
        $str = \sprintf($format, $value);
        if (! empty($this->unit)) {
            return $str.' '.$this->unit;
        }

        return $str;
    }
}
