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

declare(strict_types=1);

namespace App\Entity\AssemblySystem;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\ApiPlatform\Filter\LikeFilter;
use App\Entity\Contracts\TimeStampableInterface;
use App\Repository\DBElementRepository;
use App\Validator\Constraints\AssemblySystem\AssemblyCycle;
use App\Validator\Constraints\AssemblySystem\AssemblyInvalidBomEntry;
use App\Validator\UniqueValidatableInterface;
use Doctrine\DBAL\Types\Types;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\TimestampTrait;
use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Currency;
use App\Validator\Constraints\BigDecimal\BigDecimalPositive;
use App\Validator\Constraints\Selectable;
use Brick\Math\BigDecimal;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The AssemblyBOMEntry class represents an entry in a assembly's BOM.
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: DBElementRepository::class)]
#[ORM\Table('assembly_bom_entries')]
#[ApiResource(
    operations: [
        new Get(uriTemplate: '/assembly_bom_entries/{id}.{_format}', security: 'is_granted("read", object)',),
        new GetCollection(uriTemplate: '/assembly_bom_entries.{_format}', security: 'is_granted("@assemblies.read")',),
        new Post(uriTemplate: '/assembly_bom_entries.{_format}', securityPostDenormalize: 'is_granted("create", object)',),
        new Patch(uriTemplate: '/assembly_bom_entries/{id}.{_format}', security: 'is_granted("edit", object)',),
        new Delete(uriTemplate: '/assembly_bom_entries/{id}.{_format}', security: 'is_granted("delete", object)',),
    ],
    normalizationContext: ['groups' => ['bom_entry:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['bom_entry:write', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiResource(
    uriTemplate: '/assemblies/{id}/bom.{_format}',
    operations: [
        new GetCollection(
            openapi: new Operation(summary: 'Retrieves the BOM entries of the given assembly.'),
            security: 'is_granted("@assemblies.read")'
        )
    ],
    uriVariables: [
        'id' => new Link(fromProperty: 'bom_entries', fromClass: Assembly::class)
    ],
    normalizationContext: ['groups' => ['bom_entry:read', 'api:basic:read'], 'openapi_definition_name' => 'Read']
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ["name", 'mountnames', 'designator', "comment"])]
#[ApiFilter(RangeFilter::class, properties: ['quantity'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified', 'quantity'])]
class AssemblyBOMEntry extends AbstractDBElement implements UniqueValidatableInterface, TimeStampableInterface
{
    use TimestampTrait;

    #[Assert\Positive]
    #[ORM\Column(name: 'quantity', type: Types::FLOAT)]
    #[Groups(['bom_entry:read', 'bom_entry:write', 'import', 'simple', 'extended', 'full'])]
    protected float $quantity = 1.0;

    /**
     * @var string A comma separated list of the names, where this parts should be placed
     */
    #[ORM\Column(name: 'mountnames', type: Types::TEXT)]
    #[Groups(['bom_entry:read', 'bom_entry:write', 'import', 'simple', 'extended', 'full'])]
    protected string $mountnames = '';

    /**
     * @var string Reference mark on the circuit diagram/PCB
     */
    #[ORM\Column(name: 'designator', type: Types::TEXT)]
    #[Groups(['bom_entry:read', 'bom_entry:write', 'import', 'simple', 'extended', 'full'])]
    protected string $designator = '';

    /**
     * @var string|null An optional name describing this BOM entry (useful for non-part entries)
     */
    #[Assert\Expression('this.getPart() !== null or this.getReferencedAssembly() !== null or this.getName() !== null', message: 'validator.assembly.bom_entry.name_or_part_needed')]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Groups(['bom_entry:read', 'bom_entry:write', 'import', 'simple', 'extended', 'full'])]
    protected ?string $name = null;

    /**
     * @var string An optional comment for this BOM entry
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['bom_entry:read', 'bom_entry:write', 'import', 'extended', 'full'])]
    protected string $comment = '';

    /**
     * @var Assembly|null
     */
    #[ORM\ManyToOne(targetEntity: Assembly::class, inversedBy: 'bom_entries')]
    #[ORM\JoinColumn(name: 'id_assembly', nullable: true)]
    #[Groups(['bom_entry:read', 'bom_entry:write'])]
    protected ?Assembly $assembly = null;

    /**
     * @var Part|null The part associated with this
     */
    #[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'assembly_bom_entries')]
    #[ORM\JoinColumn(name: 'id_part')]
    #[Groups(['bom_entry:read', 'bom_entry:write', 'full'])]
    protected ?Part $part = null;

    /**
     * @var Assembly|null The associated assembly
     */
    #[Assert\Expression(
        '(this.getPart() === null or this.getReferencedAssembly() === null) and (this.getName() === null or (this.getName() != null and this.getName() != ""))',
        message: 'validator.assembly.bom_entry.only_part_or_assembly_allowed'
    )]
    #[AssemblyCycle]
    #[AssemblyInvalidBomEntry]
    #[ORM\ManyToOne(targetEntity: Assembly::class)]
    #[ORM\JoinColumn(name: 'id_referenced_assembly', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['bom_entry:read', 'bom_entry:write'])]
    protected ?Assembly $referencedAssembly = null;

    /**
     * @var BigDecimal|null The price of this non-part BOM entry
     */
    #[Assert\AtLeastOneOf([new BigDecimalPositive(), new Assert\IsNull()])]
    #[ORM\Column(type: 'big_decimal', precision: 11, scale: 5, nullable: true)]
    #[Groups(['bom_entry:read', 'bom_entry:write', 'import', 'extended', 'full'])]
    protected ?BigDecimal $price = null;

    /**
     * @var ?Currency The currency for the price of this non-part BOM entry
     */
    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn]
    #[Selectable]
    protected ?Currency $price_currency = null;

    public function __construct()
    {
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): AssemblyBOMEntry
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getMountnames(): string
    {
        return $this->mountnames;
    }

    public function setMountnames(string $mountnames): AssemblyBOMEntry
    {
        $this->mountnames = $mountnames;
        return $this;
    }

    public function getDesignator(): string
    {
        return $this->designator;
    }

    public function setDesignator(string $designator): void
    {
        $this->designator = $designator;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return trim($this->name ?? '') === '' ? null : $this->name;
    }

    /**
     * @param  string  $name
     */
    public function setName(?string $name): AssemblyBOMEntry
    {
        $this->name = trim($name ?? '') === '' ? null : $name;
        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): AssemblyBOMEntry
    {
        $this->comment = $comment;
        return $this;
    }

    public function getAssembly(): ?Assembly
    {
        return $this->assembly;
    }

    public function setAssembly(?Assembly $assembly): AssemblyBOMEntry
    {
        $this->assembly = $assembly;
        return $this;
    }

    public function getPart(): ?Part
    {
        return $this->part;
    }

    public function setPart(?Part $part): AssemblyBOMEntry
    {
        $this->part = $part;
        return $this;
    }

    public function getReferencedAssembly(): ?Assembly
    {
        return $this->referencedAssembly;
    }

    public function setReferencedAssembly(?Assembly $referencedAssembly): AssemblyBOMEntry
    {
        $this->referencedAssembly = $referencedAssembly;
        return $this;
    }

    /**
     * Returns the price of this BOM entry, if existing.
     * Prices are only valid on non-Part BOM entries.
     */
    public function getPrice(): ?BigDecimal
    {
        return $this->price;
    }

    /**
     * Sets the price of this BOM entry.
     * Prices are only valid on non-Part BOM entries.
     */
    public function setPrice(?BigDecimal $price): void
    {
        $this->price = $price;
    }

    public function getPriceCurrency(): ?Currency
    {
        return $this->price_currency;
    }

    public function setPriceCurrency(?Currency $price_currency): void
    {
        $this->price_currency = $price_currency;
    }

    /**
     * Checks whether this BOM entry is a part associated BOM entry or not.
     * @return bool True if this BOM entry is a part associated BOM entry, false otherwise.
     */
    public function isPartBomEntry(): bool
    {
        return $this->part instanceof Part;
    }

    /**
     * Checks whether this BOM entry is a assembly associated BOM entry or not.
     * @return bool True if this BOM entry is a assembly associated BOM entry, false otherwise.
     */
    public function isAssemblyBomEntry(): bool
    {
        return $this->referencedAssembly !== null;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        //Round quantity to whole numbers, if the part is not a decimal part
        if ($this->part instanceof Part && (!$this->part->getPartUnit() || $this->part->getPartUnit()->isInteger())) {
            $this->quantity = round($this->quantity);
        }
        //Non-Part BOM entries are rounded
        if (!$this->part instanceof Part) {
            $this->quantity = round($this->quantity);
        }

        //Check that the part is not the build representation part of this assembly or one of its parents
        if ($this->part && $this->part->getBuiltAssembly() instanceof Assembly) {
            //Get the associated assembly
            $associated_assembly = $this->part->getBuiltAssembly();
            //Check that it is not the same as the current assembly neither one of its parents
            $current_assembly = $this->assembly;
            while ($current_assembly) {
                if ($associated_assembly === $current_assembly) {
                    $context->buildViolation('assembly.bom_entry.can_not_add_own_builds_part')
                        ->atPath('part')
                        ->addViolation();
                }
                $current_assembly = $current_assembly->getParent();
            }
        }
    }


    public function getComparableFields(): array
    {
        return [
            'name' => $this->getName(),
            'part' => $this->getPart()?->getID(),
            'referencedAssembly' => $this->getReferencedAssembly()?->getID(),
        ];
    }
}
