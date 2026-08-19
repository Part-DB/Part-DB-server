<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Entity\Parameters;

use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiPlatform\Filter\LikeFilter;
use App\Repository\ParameterRepository;
use App\Validator\UniqueValidatableInterface;
use Doctrine\DBAL\Types\Types;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractNamedDBElement;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use function sprintf;

#[ORM\Entity(repositoryClass: ParameterRepository::class)]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'smallint')]
#[ORM\DiscriminatorMap([0 => CategoryParameter::class, 1 => CurrencyParameter::class, 2 => ProjectParameter::class,
    3 => FootprintParameter::class, 4 => GroupParameter::class, 5 => ManufacturerParameter::class,
    6 => MeasurementUnitParameter::class, 7 => PartParameter::class, 8 => StorageLocationParameter::class,
    9 => SupplierParameter::class, 10 => AttachmentTypeParameter::class,
    12 => PartCustomStateParameter::class])]
#[ORM\Table('parameters')]
#[ORM\Index(columns: ['name'], name: 'parameter_name_idx')]
#[ORM\Index(columns: ['param_group'], name: 'parameter_group_idx')]
#[ORM\Index(columns: ['type', 'element_id'], name: 'parameter_type_element_idx')]
#[ORM\Index(columns: ['definition_id', 'value_text', 'type', 'element_id'], name: 'parameter_definition_value_idx')]
#[ApiResource(
    shortName: 'Parameter',
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['parameter:read', 'parameter:read:standalone',  'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['parameter:write', 'parameter:write:standalone', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiFilter(LikeFilter::class, properties: ["name", "symbol", "unit", "group", "value_text"])]
#[ApiFilter(DateFilter::class, strategy: DateFilterInterface::EXCLUDE_NULL)]
#[ApiFilter(RangeFilter::class, properties: ["value_min", "value_typical", "value_max"])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
//This discriminator map is required for API platform to know which class to use for deserialization, when creating a new parameter.
#[DiscriminatorMap(typeProperty: '_type', mapping: self::API_DISCRIMINATOR_MAP)]
abstract class AbstractParameter extends AbstractNamedDBElement implements UniqueValidatableInterface
{
    /*
     * The discriminator map used for API platform. The key should be the same as the api platform short type (the @type JSONLD field).
     */
    private const API_DISCRIMINATOR_MAP = ["Part" => PartParameter::class,
        "AttachmentType" => AttachmentTypeParameter::class, "Category" => CategoryParameter::class, "Currency" => CurrencyParameter::class,
        "Project" => ProjectParameter::class, "Footprint" => FootprintParameter::class, "Group" => GroupParameter::class,
        "Manufacturer" => ManufacturerParameter::class, "MeasurementUnit" => MeasurementUnitParameter::class,
        "StorageLocation" => StorageLocationParameter::class, "Supplier" => SupplierParameter::class, "PartCustomState" => PartCustomStateParameter::class];

    /**
     * @var string The class of the element that can be passed to this attachment. Must be overridden in subclasses.
     */
    protected const ALLOWED_ELEMENT_CLASS = '';

    /**
     * @var string The mathematical symbol for this specification. Can be rendered pretty later. Should be short
     */
    #[Assert\Length(max: 20)]
    #[Groups(['full', 'parameter:read', 'parameter:write', 'import'])]
    #[ORM\Column(type: Types::STRING)]
    protected string $symbol = '';

    /**
     * @var float|null the guaranteed minimum value of this property
     */
    #[Assert\Type(['float', 'null'])]
    #[Assert\LessThanOrEqual(propertyPath: 'value_typical', message: 'parameters.validator.min_lesser_typical')]
    #[Assert\LessThan(propertyPath: 'value_max', message: 'parameters.validator.min_lesser_max')]
    #[Groups(['full', 'parameter:read', 'parameter:write', 'import'])]
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    protected ?float $value_min = null;

    /**
     * @var float|null the typical value of this property
     */
    #[Assert\Type(['null', 'float'])]
    #[Groups(['full', 'parameter:read', 'parameter:write', 'import'])]
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    protected ?float $value_typical = null;

    /**
     * @var float|null the maximum value of this property
     */
    #[Assert\Type(['float', 'null'])]
    #[Assert\GreaterThanOrEqual(propertyPath: 'value_typical', message: 'parameters.validator.max_greater_typical')]
    #[Groups(['full', 'parameter:read', 'parameter:write', 'import'])]
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    protected ?float $value_max = null;

    /**
     * @var string The unit in which the value values are given (e.g. V)
     */
    #[Groups(['full', 'parameter:read', 'parameter:write', 'import'])]
    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 50)]
    protected string $unit = '';

    /**
     * @var string a text value for the given property
     */
    #[Groups(['full', 'parameter:read', 'parameter:write', 'import'])]
    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 255)]
    protected string $value_text = '';

    /**
     * Optional global definition. Name, symbol and unit remain persisted snapshots for historical views, while
     * input type and choices are always read from the linked definition.
     */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['parameter:read', 'parameter:write'])]
    #[ORM\ManyToOne(targetEntity: ParameterDefinition::class, inversedBy: 'parameter_usages')]
    #[ORM\JoinColumn(name: 'definition_id', nullable: true, onDelete: 'RESTRICT')]
    protected ?ParameterDefinition $definition = null;

    /**
     * A choice explicitly requested from the Part editor. This is deliberately not persisted: the definition is
     * updated only after the complete Part form has passed validation.
     */
    private ?string $pending_definition_choice = null;

    /**
     * @var string the group this parameter belongs to
     */
    #[Groups(['full', 'parameter:read', 'parameter:write', 'import'])]
    #[ORM\Column(name: 'param_group', type: Types::STRING)]
    #[Assert\Length(max: 255)]
    protected string $group = '';

    /**
     * @var bool|null Whether this parameter should be exported as a field in the EDA HTTP library API. Null means use system default.
     */
    #[Groups(['full', 'parameter:read', 'parameter:write', 'import'])]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => null])]
    protected ?bool $eda_visibility = null;

    /**
     * @var bool|null Whether the exported EDA field should be visible in the schematic symbol
     *                (sets the KiCad field's "visible" flag). Null means use system default.
     */
    #[Groups(['full', 'parameter:read', 'parameter:write', 'import'])]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => null])]
    protected ?bool $eda_symbol_visibility = null;

    /**
     * Mapping is done in subclasses.
     *
     * @var AbstractDBElement|null the element to which this parameter belongs to
     */
    #[Groups(['parameter:read:standalone', 'parameter:write:standalone'])]
    protected ?AbstractDBElement $element = null;

    public function __construct()
    {
        if ('' === static::ALLOWED_ELEMENT_CLASS) {
            throw new LogicException('An *Attachment class must override the ALLOWED_ELEMENT_CLASS const!');
        }
    }

    public function updateTimestamps(): void
    {
        parent::updateTimestamps();
        if ($this->element instanceof AbstractNamedDBElement) {
            $this->element->updateTimestamps();
        }
    }

    /**
     * Returns the element this parameter belongs to.
     */
    public function getElement(): ?AbstractDBElement
    {
        return $this->element;
    }

    /**
     * Returns the optional global definition linked to this parameter.
     */
    public function getDefinition(): ?ParameterDefinition
    {
        return $this->definition;
    }

    /**
     * Links a definition and captures its current name, symbol and unit as historical snapshots.
     */
    public function setDefinition(?ParameterDefinition $definition): self
    {
        $this->synchronizeDefinitionReference($definition);
        if ($definition instanceof ParameterDefinition) {
            $this->refreshSnapshotFromDefinition();

            if (ParameterDefinition::INPUT_TYPE_CHOICE === $definition->getInputType() && '' !== $this->value_text) {
                $canonical_choice = $definition->findCanonicalChoice($this->value_text);
                if (null !== $canonical_choice) {
                    $this->value_text = $canonical_choice;
                }
            }
        }

        return $this;
    }

    /**
     * Restores only the historical association without replacing the independently persisted metadata snapshots.
     * This is intended for the TimeTravel infrastructure.
     */
    public function restoreDefinitionReference(?ParameterDefinition $definition): self
    {
        $this->synchronizeDefinitionReference($definition);

        return $this;
    }

    private function synchronizeDefinitionReference(?ParameterDefinition $definition): void
    {
        if ($this->definition === $definition) {
            $definition?->addParameterUsage($this);

            return;
        }

        $previous_definition = $this->definition;
        $this->definition = $definition;

        $previous_definition?->removeParameterUsage($this);
        $definition?->addParameterUsage($this);
    }

    /**
     * Explicitly refreshes the persisted name, symbol and unit snapshots from the linked definition.
     */
    public function refreshSnapshotFromDefinition(): self
    {
        if (!$this->definition instanceof ParameterDefinition) {
            return $this;
        }

        $this->name = $this->definition->getName();
        $this->symbol = $this->definition->getSymbol();
        $this->unit = $this->definition->getUnit();

        return $this;
    }

    public function getSnapshotName(): string
    {
        return $this->name;
    }

    public function getEffectiveName(): string
    {
        return $this->definition?->getName() ?? $this->name;
    }

    /**
     * Return a formatted string version of the values of the string.
     * Based on the set values it can return something like this: 34 V (12 V ... 50 V) [Text].
     */
    #[Groups(['parameter:read', 'full'])]
    #[SerializedName('formatted')]
    public function getFormattedValue(bool $latex_formatted = false): string
    {
        return $this->formatValue($this->unit, $latex_formatted);
    }

    /**
     * Formats the current value using metadata from the linked definition when available.
     */
    public function getEffectiveFormattedValue(bool $latex_formatted = false): string
    {
        return $this->formatValue($this->getEffectiveUnit(), $latex_formatted);
    }

    private function formatValue(string $unit, bool $latex_formatted): string
    {
        //If we just only have text value, return early
        if (null === $this->value_typical && null === $this->value_min && null === $this->value_max) {
            return $this->value_text;
        }

        $str = '';
        $bracket_opened = false;
        if ($this->value_typical !== null) {
            $str .= $this->formatWithExplicitUnit($this->value_typical, $unit, with_latex: $latex_formatted);
            if ($this->value_min || $this->value_max) {
                $bracket_opened = true;
                $str .= ' (';
            }
        }

        if ($this->value_max !== null && $this->value_min !== null) {
            $str .= $this->formatWithExplicitUnit($this->value_min, $unit, with_latex: $latex_formatted).' ... '
                .$this->formatWithExplicitUnit($this->value_max, $unit, with_latex: $latex_formatted);
        } elseif ($this->value_max !== null) {
            $str .= 'max. '.$this->formatWithExplicitUnit($this->value_max, $unit, with_latex: $latex_formatted);
        } elseif ($this->value_min !== null) {
            $str .= 'min. '.$this->formatWithExplicitUnit($this->value_min, $unit, with_latex: $latex_formatted);
        }

        //Add closing bracket
        if ($bracket_opened) {
            $str .= ')';
        }

        if ($this->value_text !== '' && $this->value_text !== '0') {
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
        if (!is_a($element, static::ALLOWED_ELEMENT_CLASS)) {
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
     */
    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function getSnapshotSymbol(): string
    {
        return $this->symbol;
    }

    public function getEffectiveSymbol(): string
    {
        return $this->definition?->getSymbol() ?? $this->symbol;
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
     */
    public function getValueTypical(): ?float
    {
        return $this->value_typical;
    }

    /**
     * Return a formatted version with the minimum value with the unit of this parameter.
     */
    public function getValueTypicalWithUnit(bool $with_latex = false): string
    {
        return $this->formatWithUnit($this->value_typical, with_latex: $with_latex);
    }

    /**
     * Return a formatted version with the maximum value with the unit of this parameter.
     */
    public function getValueMaxWithUnit(bool $with_latex = false): string
    {
        return $this->formatWithUnit($this->value_max, with_latex: $with_latex);
    }

    /**
     * Return a formatted version with the typical value with the unit of this parameter.
     */
    public function getValueMinWithUnit(bool $with_latex = false): string
    {
        return $this->formatWithUnit($this->value_min, with_latex: $with_latex);
    }

    /**
     * Sets the typical value of this property.
     *
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
     */
    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getSnapshotUnit(): string
    {
        return $this->unit;
    }

    public function getEffectiveUnit(): string
    {
        return $this->definition?->getUnit() ?? $this->unit;
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
    public function setValueText(?string $value_text): self
    {
        $value_text ??= '';

        if ($this->definition instanceof ParameterDefinition
            && ParameterDefinition::INPUT_TYPE_CHOICE === $this->definition->getInputType()
            && '' !== $value_text) {
            $canonical_choice = $this->definition->findCanonicalChoice($value_text);
            if (null !== $canonical_choice) {
                $value_text = $canonical_choice;
            }
        }

        $this->value_text = $value_text;

        return $this;
    }

    public function requestPendingDefinitionChoice(?string $choice): self
    {
        $choice = null === $choice ? '' : trim($choice);
        $this->pending_definition_choice = '' === $choice ? null : $choice;

        return $this;
    }

    public function getPendingDefinitionChoice(): ?string
    {
        return $this->pending_definition_choice;
    }

    public function clearPendingDefinitionChoice(): self
    {
        $this->pending_definition_choice = null;

        return $this;
    }

    #[Groups(['parameter:read'])]
    #[SerializedName('input_type')]
    public function getEffectiveInputType(): string
    {
        return $this->definition?->getInputType() ?? ParameterDefinition::INPUT_TYPE_TEXT;
    }

    /** @return list<string> */
    #[Groups(['parameter:read'])]
    #[SerializedName('choices')]
    public function getEffectiveChoices(): array
    {
        return $this->definition?->getChoices() ?? [];
    }

    public function hasEffectiveChoices(): bool
    {
        return ParameterDefinition::INPUT_TYPE_CHOICE === $this->getEffectiveInputType()
            && [] !== $this->getEffectiveChoices();
    }

    public function getEffectiveChoicesText(): string
    {
        return implode("\n", $this->getEffectiveChoices());
    }

    /**
     * Return a string representation and (if possible) with its unit.
     */
    protected function formatWithUnit(float $value, string $format = '%g', bool $with_latex = false): string
    {
        return $this->formatWithExplicitUnit($value, $this->unit, $format, $with_latex);
    }

    private function formatWithExplicitUnit(float $value, string $unit, string $format = '%g', bool $with_latex = false): string
    {
        $str = sprintf($format, $value);
        if ('' !== $unit) {

            if (!$with_latex) {
                $formatted_unit = $unit;
            } else {
                //Escape the percentage sign for convenience (as latex uses it as comment and it is often used in units)
                $escaped = preg_replace('/\\\\?%/', "\\\\%", $unit);

                $formatted_unit = '$\mathrm{'.$escaped.'}$';
            }

            return $str.' '.$formatted_unit;
        }

        return $str;
    }

    /**
     * Returns the class of the element that is allowed to be associated with this attachment.
     * @return string
     */
    public function getElementClass(): string
    {
        return static::ALLOWED_ELEMENT_CLASS;
    }

    public function isEdaVisibility(): ?bool
    {
        return $this->eda_visibility;
    }

    /**
     * @return $this
     */
    public function setEdaVisibility(?bool $eda_visibility): self
    {
        $this->eda_visibility = $eda_visibility;

        return $this;
    }

    public function isEdaSymbolVisibility(): ?bool
    {
        return $this->eda_symbol_visibility;
    }

    /**
     * @return $this
     */
    public function setEdaSymbolVisibility(?bool $eda_symbol_visibility): self
    {
        $this->eda_symbol_visibility = $eda_symbol_visibility;

        return $this;
    }

    #[Assert\Callback]
    public function validateDefinitionUsage(ExecutionContextInterface $context): void
    {
        if (!$this->definition instanceof ParameterDefinition
            || ParameterDefinition::INPUT_TYPE_CHOICE !== $this->definition->getInputType()
            || '' === $this->value_text) {
            return;
        }

        $canonical_choice = $this->definition->findCanonicalChoice($this->value_text);
        if (null === $canonical_choice) {
            if ($this->pending_definition_choice === $this->value_text) {
                return;
            }

            $context->buildViolation('parameter.validator.value_not_allowed')
                ->atPath('value_text')
                ->addViolation();

            return;
        }

        if ($canonical_choice !== $this->value_text) {
            $context->buildViolation('parameter.validator.value_not_canonical')
                ->atPath('value_text')
                ->addViolation();
        }
    }

    public function getComparableFields(): array
    {
        return ['name' => $this->getEffectiveName(), 'group' => $this->group, 'element' => $this->element?->getId()];
    }
}
