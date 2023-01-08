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

namespace App\Entity\LogSystem;

use App\Entity\Parts\PartLot;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class PartStockChangedLogEntry extends AbstractLogEntry
{
    public const TYPE_ADD = "add";
    public const TYPE_WITHDRAW = "withdraw";
    public const TYPE_MOVE = "move";

    protected string $typeString = 'part_stock_changed';

    protected const COMMENT_MAX_LENGTH = 300;

    /**
     * Creates a new part stock changed log entry.
     * @param  string  $type The type of the log entry. One of the TYPE_* constants.
     * @param  PartLot  $lot The part lot which has been changed.
     * @param  float  $old_stock The old stock of the lot.
     * @param  float  $new_stock The new stock of the lot.
     * @param  float $new_total_part_instock The new total instock of the part.
     * @param  string  $comment The comment associated with the change.
     * @param  PartLot|null  $move_to_target The target lot if the type is TYPE_MOVE.
     */
    protected function __construct(string $type, PartLot $lot, float $old_stock, float $new_stock, float $new_total_part_instock, string $comment, ?PartLot $move_to_target = null)
    {
        parent::__construct();

        if (!in_array($type, [self::TYPE_ADD, self::TYPE_WITHDRAW, self::TYPE_MOVE], true)) {
            throw new \InvalidArgumentException('Invalid type for PartStockChangedLogEntry!');
        }

        //Same as every other element change log entry
        $this->level = self::LEVEL_INFO;

        $this->setTargetElement($lot);

        $this->typeString = 'part_stock_changed';
        $this->extra = array_merge($this->extra, [
            't' => $this->typeToShortType($type),
            'o' => $old_stock,
            'n' => $new_stock,
            'p' => $new_total_part_instock,
        ]);
        if (!empty($comment)) {
            $this->extra['c'] = mb_strimwidth($comment, 0, self::COMMENT_MAX_LENGTH, '...');
        }

        if ($move_to_target) {
            if ($type !== self::TYPE_MOVE) {
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
     * @return static
     */
    public static function add(PartLot $lot, float $old_stock, float $new_stock, float $new_total_part_instock, string $comment): self
    {
        return new self(self::TYPE_ADD, $lot, $old_stock, $new_stock, $new_total_part_instock, $comment);
    }

    /**
     * Creates a new log entry for withdrawing stock from a lot.
     * @param  PartLot  $lot The part lot which has been changed.
     * @param  float  $old_stock The old stock of the lot.
     * @param  float  $new_stock The new stock of the lot.
     * @param  float  $new_total_part_instock The new total instock of the part.
     * @param  string  $comment The comment associated with the change.
     * @return static
     */
    public static function withdraw(PartLot $lot, float $old_stock, float $new_stock, float $new_total_part_instock, string $comment): self
    {
        return new self(self::TYPE_WITHDRAW, $lot, $old_stock, $new_stock, $new_total_part_instock, $comment);
    }

    /**
     * Creates a new log entry for moving stock from a lot to another lot.
     * @param  PartLot  $lot The part lot which has been changed.
     * @param  float  $old_stock The old stock of the lot.
     * @param  float  $new_stock The new stock of the lot.
     * @param  float  $new_total_part_instock The new total instock of the part.
     * @param  string  $comment The comment associated with the change.
     * @param  PartLot  $move_to_target The target lot.
     */
    public static function move(PartLot $lot, float $old_stock, float $new_stock, float $new_total_part_instock, string $comment, PartLot $move_to_target): self
    {
        return new self(self::TYPE_MOVE, $lot, $old_stock, $new_stock, $new_total_part_instock, $comment, $move_to_target);
    }

    /**
     * Returns the instock change type of this entry
     * @return string One of the TYPE_* constants.
     */
    public function getInstockChangeType(): string
    {
        return $this->shortTypeToType($this->extra['t']);
    }

    /**
     * Returns the old stock of the lot.
     * @return float
     */
    public function getOldStock(): float
    {
        return $this->extra['o'];
    }

    /**
     * Returns the new stock of the lot.
     * @return float
     */
    public function getNewStock(): float
    {
        return $this->extra['n'];
    }

    /**
     * Returns the new total instock of the part.
     * @return float
     */
    public function getNewTotalPartInstock(): float
    {
        return $this->extra['p'];
    }

    /**
     * Returns the comment associated with the change.
     * @return string
     */
    public function getComment(): string
    {
        return $this->extra['c'] ?? '';
    }

    /**
     * Gets the difference between the old and the new stock value of the lot as a positive number.
     * @return float
     */
    public function getChangeAmount(): float
    {
        return abs($this->getNewStock() - $this->getOldStock());
    }

    /**
     * Returns the target lot ID (where the instock was moved to) if the type is TYPE_MOVE.
     * @return int|null
     */
    public function getMoveToTargetID(): ?int
    {
        return $this->extra['m'] ?? null;
    }

    /**
     * Converts the human-readable type (TYPE_* consts) to the version stored in DB
     * @param  string  $type
     * @return string
     */
    protected function typeToShortType(string $type): string
    {
        switch ($type) {
            case self::TYPE_ADD:
                return 'a';
            case self::TYPE_WITHDRAW:
                return 'w';
            case self::TYPE_MOVE:
                return 'm';
            default:
                throw new \InvalidArgumentException('Invalid type: '.$type);
        }
    }

    /**
     * Converts the short type stored in DB to the human-readable type (TYPE_* consts).
     * @param  string  $short_type
     * @return string
     */
    protected function shortTypeToType(string $short_type): string
    {
        switch ($short_type) {
            case 'a':
                return self::TYPE_ADD;
            case 'w':
                return self::TYPE_WITHDRAW;
            case 'm':
                return self::TYPE_MOVE;
            default:
                throw new \InvalidArgumentException('Invalid short type: '.$short_type);
        }
    }
}