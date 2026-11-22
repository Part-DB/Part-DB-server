<?php

declare(strict_types=1);

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

namespace App\Entity\AlternativeNames;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\PartCustomState;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\ProjectSystem\Project;
use App\Entity\UserSystem\Group;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'alternative_names')]
#[ORM\Index(name: 'idx_altname_lookup', columns: ['type', 'name'])]
#[ORM\Index(name: 'idx_altname_element', columns: ['type', 'element_id'])]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'smallint')]
#[ORM\DiscriminatorMap([
    0 => CategoryAlternativeName::class,
    1 => FootprintAlternativeName::class,
    2 => ManufacturerAlternativeName::class,
    3 => SupplierAlternativeName::class,
    4 => StorageLocationAlternativeName::class,
    5 => MeasurementUnitAlternativeName::class,
    6 => AttachmentTypeAlternativeName::class,
    7 => PartCustomStateAlternativeName::class,
    8 => GroupAlternativeName::class,
    9 => CurrencyAlternativeName::class,
    10 => ProjectAlternativeName::class,
])]
abstract class AbstractAlternativeName
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::INTEGER)]
    private int $sort_index = 0;

    /**
     * The element this alternative name belongs to. Defined with concrete FK in each subclass.
     */
    protected ?AbstractDBElement $element = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSortIndex(): int
    {
        return $this->sort_index;
    }

    public function setSortIndex(int $sort_index): static
    {
        $this->sort_index = $sort_index;
        return $this;
    }

    public function getElement(): ?AbstractDBElement
    {
        return $this->element;
    }

    public function setElement(AbstractDBElement $element): static
    {
        $this->element = $element;
        return $this;
    }
}
