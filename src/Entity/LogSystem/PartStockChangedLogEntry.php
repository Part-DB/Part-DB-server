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
namespace App\Entity\LogSystem;

use App\Entity\Parts\PartLot;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PartStockChangedLogEntry extends AbstractLogEntry
{
    protected string $typeString = 'part_stock_changed';

    protected const COMMENT_MAX_LENGTH = 300;

    /**
     * Creates a new part stock changed log entry.
     * @param  PartStockChangeType $type The type of the log entry.
     * @param  PartLot  $lot The part lot which has been changed.
     * @param  float  $old_stock The old stock of the lot.
     * @param  float  $new_stock The new stock of the lot.
     * @param  float $new_total_part_instock The new total instock of the part.
     * @param  string  $comment The comment associated with the change.
     * @param  PartLot|null  $move_to_target The target lot if the type is TYPE_MOVE.
     * @param  \DateTimeInterface|null  $action_timestamp The optional timestamp, where the action happened. Useful if the action happened in the past, and the log entry is created afterwards.
     */
    protected function __construct(PartStockChangeType $type, PartLot $lot, float $old_stock, float $new_stock, float $new_total_part_instock, string $comment, ?PartLot $move_to_target = null,
        ?\DateTimeInterface $action_timestamp = null)
    {
        parent::__construct();

        //Same as every other element change log entry
        $this->level = LogLevel::INFO;

        $this->setTargetElement($lot);

        $this->typeString = 'part_stock_changed';
        $this->extra = array_merge($this->extra, [
            't' => $type->toExtraShortType(),
            'o' => $old_stock,
            'n' => $new_stock,
            'p' => $new_total_part_instock,
        ]);
        if ($comment !== '') {
            $this->extra['c'] = mb_strimwidth($comment, 0, self::COMMENT_MAX_LENGTH, '...');
        }

        if ($action_timestamp instanceof \DateTimeInterface) {
            //The action timestamp is saved as an ISO 8601 string
            $this->extra['a'] = $action_timestamp->format(\DateTimeInterface::ATOM);
        }

        if ($move_to_target instanceof PartLot) {
            if ($type !== PartStockChangeType::MOVE) {
                throw new \InvalidArgumentException('The move_to_target parameter can only be set if the type is "move"!');
            }

            $this->extra['m'] = $move_to_target->getID();
        }
    }

    /**
     * Creates a new log entry for adding stock to a lot.
     * @param  PartLot  $lot The part lot which has been changed.
     * @param  float  $old_stock The old stock of the lot.
     * @param  float  $new_stock The new stock of the lot.
     * @param  float  $new_total_part_instock The new total instock of the part.
     * @param  string  $comment The comment associated with the change.
     * @param  \DateTimeInterface|null  $action_timestamp The optional timestamp, where the action happened. Useful if the action happened in the past, and the log entry is created afterwards.
     * @return self
     */
    public static function add(PartLot $lot, float $old_stock, float $new_stock, float $new_total_part_instock, string $comment, ?\DateTimeInterface $action_timestamp = null): self
    {
        return new self(PartStockChangeType::ADD, $lot, $old_stock, $new_stock, $new_total_part_instock, $comment, action_timestamp: $action_timestamp);
    }

    /**
     * Creates a new log entry for withdrawing stock from a lot.
     * @param  PartLot  $lot The part lot which has been changed.
     * @param  float  $old_stock The old stock of the lot.
     * @param  float  $new_stock The new stock of the lot.
     * @param  float  $new_total_part_instock The new total instock of the part.
     * @param  string  $comment The comment associated with the change.
     * @param  \DateTimeInterface|null  $action_timestamp The optional timestamp, where the action happened. Useful if the action happened in the past, and the log entry is created afterwards.
     * @return self
     */
    public static function withdraw(PartLot $lot, float $old_stock, float $new_stock, float $new_total_part_instock, string $comment, ?\DateTimeInterface $action_timestamp = null): self
    {
        return new self(PartStockChangeType::WITHDRAW, $lot, $old_stock, $new_stock, $new_total_part_instock, $comment, action_timestamp: $action_timestamp);
    }

    /**
     * Creates a new log entry for moving stock from a lot to another lot.
     * @param  PartLot  $lot The part lot which has been changed.
     * @param  float  $old_stock The old stock of the lot.
     * @param  float  $new_stock The new stock of the lot.
     * @param  float  $new_total_part_instock The new total instock of the part.
     * @param  string  $comment The comment associated with the change.
     * @param  PartLot  $move_to_target The target lot.
     * @param  \DateTimeInterface|null  $action_timestamp The optional timestamp, where the action happened. Useful if the action happened in the past, and the log entry is created afterwards.
     * @return self
     */
    public static function move(PartLot $lot, float $old_stock, float $new_stock, float $new_total_part_instock, string $comment, PartLot $move_to_target, ?\DateTimeInterface $action_timestamp = null): self
    {
        return new self(PartStockChangeType::MOVE, $lot, $old_stock, $new_stock, $new_total_part_instock, $comment, $move_to_target, action_timestamp:  $action_timestamp);
    }

    /**
     * Returns the instock change type of this entry
     * @return PartStockChangeType
     */
    public function getInstockChangeType(): PartStockChangeType
    {
        return PartStockChangeType::fromExtraShortType($this->extra['t']);
    }

    /**
     * Returns the old stock of the lot.
     */
    public function getOldStock(): float
    {
        return $this->extra['o'];
    }

    /**
     * Returns the new stock of the lot.
     */
    public function getNewStock(): float
    {
        return $this->extra['n'];
    }

    /**
     * Returns the new total instock of the part.
     */
    public function getNewTotalPartInstock(): float
    {
        return $this->extra['p'];
    }

    /**
     * Returns the comment associated with the change.
     */
    public function getComment(): string
    {
        return $this->extra['c'] ?? '';
    }

    /**
     * Gets the difference between the old and the new stock value of the lot as a positive number.
     */
    public function getChangeAmount(): float
    {
        return abs($this->getNewStock() - $this->getOldStock());
    }

    /**
     * Returns the target lot ID (where the instock was moved to) if the type is TYPE_MOVE.
     */
    public function getMoveToTargetID(): ?int
    {
        return $this->extra['m'] ?? null;
    }

    /**
     * Returns the timestamp when this action was performed and not when the log entry was created.
     * This is useful if the action happened in the past, and the log entry is created afterwards.
     * If the timestamp is not set, null is returned.
     * @return \DateTimeInterface|null
     */
    public function getActionTimestamp(): ?\DateTimeInterface
    {
        if (!empty($this->extra['a'])) {
            return \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $this->extra['a']);
        }
        return null;
    }
}
