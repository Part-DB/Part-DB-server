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
namespace App\Helpers\Assemblies;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\AssemblySystem\Assembly;
use App\Entity\AssemblySystem\AssemblyBOMEntry;
use App\Validator\Constraints\AssemblySystem\ValidAssemblyBuildRequest;

/**
 * @see \App\Tests\Helpers\Assemblies\AssemblyBuildRequestTest
 */
#[ValidAssemblyBuildRequest]
final class AssemblyBuildRequest
{
    private readonly int $number_of_builds;

    /**
     * @var array<int, float>
     */
    private array $withdraw_amounts = [];

    private string $comment = '';

    private ?PartLot $builds_lot = null;

    private bool $add_build_to_builds_part = false;

    private bool $dont_check_quantity = false;

    /**
     * @param  Assembly $assembly The assembly that should be build
     * @param  int  $number_of_builds The number of builds that should be created
     */
    public function __construct(private readonly Assembly $assembly, int $number_of_builds)
    {
        if ($number_of_builds < 1) {
            throw new \InvalidArgumentException('Number of builds must be at least 1!');
        }
        $this->number_of_builds = $number_of_builds;

        $this->initializeArray();

        //By default, use the first available lot of builds part if there is one.
        if($assembly->getBuildPart() instanceof Part) {
            $this->add_build_to_builds_part = true;
            foreach( $assembly->getBuildPart()->getPartLots() as $lot) {
                if (!$lot->isInstockUnknown()) {
                    $this->builds_lot = $lot;
                    break;
                }
            }
        }
    }

    private function initializeArray(): void
    {
        //Completely reset the array
        $this->withdraw_amounts = [];

        //Now create an array for each BOM entry
        foreach ($this->getPartBomEntries() as $bom_entry) {
            $remaining_amount = $this->getNeededAmountForBOMEntry($bom_entry);
            foreach($this->getPartLotsForBOMEntry($bom_entry) as $lot) {
                //If the lot has instock use it for the build
                $this->withdraw_amounts[$lot->getID()] = min($remaining_amount, $lot->getAmount());
                $remaining_amount -= max(0, $this->withdraw_amounts[$lot->getID()]);
            }
        }
    }

    /**
     * Ensure that the assemblyBOMEntry belongs to the assembly, otherwise throw an exception.
     */
    private function ensureBOMEntryValid(AssemblyBOMEntry $entry): void
    {
        if ($entry->getAssembly() !== $this->assembly) {
            throw new \InvalidArgumentException('The given BOM entry does not belong to the assembly!');
        }
    }

    /**
     * Returns the partlot where the builds should be added to, or null if it should not be added to any lot.
     */
    public function getBuildsPartLot(): ?PartLot
    {
        return $this->builds_lot;
    }

    /**
     * Return if the builds should be added to the builds part of this assembly as new stock
     */
    public function getAddBuildsToBuildsPart(): bool
    {
        return $this->add_build_to_builds_part;
    }

    /**
     * Set if the builds should be added to the builds part of this assembly as new stock
     * @return $this
     */
    public function setAddBuildsToBuildsPart(bool $new_value): self
    {
        $this->add_build_to_builds_part = $new_value;

        if ($new_value === false) {
            $this->builds_lot = null;
        }

        return $this;
    }

    /**
     * Set the partlot where the builds should be added to, or null if it should not be added to any lot.
     * The part lot must belong to the assembly build part, or an exception is thrown!
     * @return $this
     */
    public function setBuildsPartLot(?PartLot $new_part_lot): self
    {
        //Ensure that this new_part_lot belongs to the assembly
        if (($new_part_lot instanceof PartLot && $new_part_lot->getPart() !== $this->assembly->getBuildPart()) || !$this->assembly->getBuildPart() instanceof Part) {
            throw new \InvalidArgumentException('The given part lot does not belong to the assemblies build part!');
        }

        if ($new_part_lot instanceof PartLot) {
            $this->setAddBuildsToBuildsPart(true);
        }

        $this->builds_lot = $new_part_lot;

        return $this;
    }

    /**
     * Returns the comment where the user can write additional information about the build.
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Sets the comment where the user can write additional information about the build.
     */
    public function setComment(string $comment): void
    {
        $this->comment = $comment;
    }

    /**
     * Returns the amount of parts that should be withdrawn from the given lot for the corresponding BOM entry.
     * @param PartLot|int $lot The part lot (or the ID of the part lot) for which the withdrawal amount should be got
     */
    public function getLotWithdrawAmount(PartLot|int $lot): float
    {
        $lot_id = $lot instanceof PartLot ? $lot->getID() : $lot;

        if (! array_key_exists($lot_id, $this->withdraw_amounts)) {
            throw new \InvalidArgumentException('The given lot is not in the withdraw amounts array!');
        }

        return $this->withdraw_amounts[$lot_id];
    }

    /**
     * Sets the amount of parts that should be withdrawn from the given lot for the corresponding BOM entry.
     * @param PartLot|int $lot The part lot (or the ID of the part lot) for which the withdrawal amount should be got
     * @return $this
     */
    public function setLotWithdrawAmount(PartLot|int $lot, float $amount): self
    {
        if ($lot instanceof PartLot) {
            $lot_id = $lot->getID();
        } elseif (is_int($lot)) {
            $lot_id = $lot;
        } else {
            throw new \InvalidArgumentException('The given lot must be an instance of PartLot or an ID of a PartLot!');
        }

        $this->withdraw_amounts[$lot_id] = $amount;

        return $this;
    }

    /**
     * Returns the sum of all withdraw amounts for the given BOM entry.
     */
    public function getWithdrawAmountSum(AssemblyBOMEntry $entry): float
    {
        $this->ensureBOMEntryValid($entry);

        $sum = 0;
        foreach ($this->getPartLotsForBOMEntry($entry) as $lot) {
            $sum += $this->getLotWithdrawAmount($lot);
        }

        if ($entry->getPart() && !$entry->getPart()->useFloatAmount()) {
            $sum = round($sum);
        }

        return $sum;
    }

    /**
     * Returns the number of available lots to take stock from for the given BOM entry.
     * @return PartLot[]|null Returns null if the entry is a non-part BOM entry
     */
    public function getPartLotsForBOMEntry(AssemblyBOMEntry $assemblyBOMEntry): ?array
    {
        $this->ensureBOMEntryValid($assemblyBOMEntry);

        if (!$assemblyBOMEntry->getPart() instanceof Part) {
            return null;
        }

        //Filter out all lots which have unknown instock
        return $assemblyBOMEntry->getPart()->getPartLots()->filter(fn (PartLot $lot) => !$lot->isInstockUnknown())->toArray();
    }

    /**
     * Returns the needed amount of parts for the given BOM entry.
     */
    public function getNeededAmountForBOMEntry(AssemblyBOMEntry $entry): float
    {
        $this->ensureBOMEntryValid($entry);

        return $entry->getQuantity() * $this->number_of_builds;
    }

    /**
     * Returns the list of all bom entries.
     * @return AssemblyBOMEntry[]
     */
    public function getBomEntries(): array
    {
        return $this->assembly->getBomEntries()->toArray();
    }

    /**
     * Returns all part bom entries.
     * @return AssemblyBOMEntry[]
     */
    public function getPartBomEntries(): array
    {
        return $this->assembly->getBomEntries()->filter(fn(AssemblyBOMEntry $entry) => $entry->isPartBomEntry())->toArray();
    }

    /**
     * Returns which assembly should be build
     */
    public function getAssembly(): Assembly
    {
        return $this->assembly;
    }

    /**
     * Returns the number of builds that should be created.
     */
    public function getNumberOfBuilds(): int
    {
        return $this->number_of_builds;
    }

    /**
     * If Set to true, the given withdraw amounts are used without any checks for requirements.
     * @return bool
     */
    public function isDontCheckQuantity(): bool
    {
        return $this->dont_check_quantity;
    }

    /**
     * Set to true, the given withdraw amounts are used without any checks for requirements.
     * @param  bool  $dont_check_quantity
     * @return $this
     */
    public function setDontCheckQuantity(bool $dont_check_quantity): AssemblyBuildRequest
    {
        $this->dont_check_quantity = $dont_check_quantity;
        return $this;
    }


}
