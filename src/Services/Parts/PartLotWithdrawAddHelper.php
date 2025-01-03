<?php

declare(strict_types=1);

namespace App\Services\Parts;

use App\Entity\Parts\StorageLocation;
use App\Entity\LogSystem\PartStockChangedLogEntry;
use App\Entity\Parts\PartLot;
use App\Services\LogSystem\EventCommentHelper;
use App\Services\LogSystem\EventLogger;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see \App\Tests\Services\Parts\PartLotWithdrawAddHelperTest
 */
final class PartLotWithdrawAddHelper
{
    public function __construct(private readonly EventLogger $eventLogger,
        private readonly EventCommentHelper $eventCommentHelper, private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Checks whether the given part can
     */
    public function canAdd(PartLot $partLot): bool
    {
        //We cannot add or withdraw parts from lots with unknown instock value.
        if($partLot->isInstockUnknown()) {
            return false;
        }

        //So far all other restrictions are defined at the storelocation level
        if(!$partLot->getStorageLocation() instanceof StorageLocation) {
            return true;
        }
        //We can not add parts if the storage location of the lot is marked as full
        return !$partLot->getStorageLocation()->isFull();
    }

    public function canWithdraw(PartLot $partLot): bool
    {
        //We cannot add or withdraw parts from lots with unknown instock value.
        if ($partLot->isInstockUnknown()) {
            return false;
        }
        //Part must contain more than 0 parts
        return $partLot->getAmount() > 0;
    }

    /**
     * Withdraw the specified amount of parts from the given part lot.
     * Please note that the changes are not flushed to DB yet, you have to do this yourself
     * @param  PartLot  $partLot The partLot from which the instock should be taken (which value should be decreased)
     * @param  float  $amount The amount of parts that should be taken from the part lot
     * @param  string|null  $comment The optional comment describing the reason for the withdrawal
     * @param  \DateTimeInterface|null  $action_timestamp The optional timestamp, where the action happened. Useful if the action happened in the past, and the log entry is created afterwards.
     * @param bool  $delete_lot_if_empty If true, the part lot will be deleted if the amount is 0 after the withdrawal.
     */
    public function withdraw(PartLot $partLot, float $amount, ?string $comment = null, ?\DateTimeInterface $action_timestamp = null, bool $delete_lot_if_empty = false): void
    {
        //Ensure that amount is positive
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $part = $partLot->getPart();

        //Check whether we have to round the amount
        if (!$part->useFloatAmount()) {
            $amount = round($amount);
        }

        //Ensure that we can withdraw from the part lot
        if (!$this->canWithdraw($partLot)) {
            throw new \RuntimeException("Cannot withdraw from this part lot!");
        }

        //Ensure that there is enough stock to withdraw
        if ($amount > $partLot->getAmount()) {
            throw new \RuntimeException('Not enough stock to withdraw!');
        }

        //Subtract the amount from the part lot
        $oldAmount = $partLot->getAmount();
        $partLot->setAmount($oldAmount - $amount);

        $event = PartStockChangedLogEntry::withdraw($partLot, $oldAmount, $partLot->getAmount(), $part->getAmountSum() , $comment, $action_timestamp);
        $this->eventLogger->log($event);

        //Apply the comment also to global events, so it gets associated with the elementChanged log entry
        if (!$this->eventCommentHelper->isMessageSet() && ($comment !== null && $comment !== '')) {
            $this->eventCommentHelper->setMessage($comment);
        }

        if ($delete_lot_if_empty && $partLot->getAmount() === 0.0) {
            $this->entityManager->remove($partLot);
        }
    }

    /**
     * Add the specified amount of parts to the given part lot.
     * Please note that the changes are not flushed to DB yet, you have to do this yourself
     * @param  PartLot  $partLot The partLot from which the instock should be taken (which value should be decreased)
     * @param  float  $amount The amount of parts that should be taken from the part lot
     * @param  string|null  $comment The optional comment describing the reason for the withdrawal
     * @param  \DateTimeInterface|null  $action_timestamp The optional timestamp, where the action happened. Useful if the action happened in the past, and the log entry is created afterwards.
     * @return PartLot The modified part lot
     */
    public function add(PartLot $partLot, float $amount, ?string $comment = null, ?\DateTimeInterface $action_timestamp = null): PartLot
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $part = $partLot->getPart();

        //Check whether we have to round the amount
        if (!$part->useFloatAmount()) {
            $amount = round($amount);
        }

        //Ensure that we can add to the part lot
        if (!$this->canAdd($partLot)) {
            throw new \RuntimeException("Cannot add to this part lot!");
        }

        $oldAmount = $partLot->getAmount();
        $partLot->setAmount($oldAmount + $amount);

        $event = PartStockChangedLogEntry::add($partLot, $oldAmount, $partLot->getAmount(), $part->getAmountSum() , $comment, $action_timestamp);
        $this->eventLogger->log($event);

        //Apply the comment also to global events, so it gets associated with the elementChanged log entry
        if (!$this->eventCommentHelper->isMessageSet() && ($comment !== null && $comment !== '')) {
            $this->eventCommentHelper->setMessage($comment);
        }

        return $partLot;
    }

    /**
     * Move the specified amount of parts from the given source part lot to the given target part lot.
     * Please note that the changes are not flushed to DB yet, you have to do this yourself
     * @param  PartLot  $origin The part lot from which the parts should be taken
     * @param  PartLot  $target The part lot to which the parts should be added
     * @param  float  $amount The amount of parts that should be moved
     * @param  string|null  $comment A comment describing the reason for the move
     * @param  \DateTimeInterface|null  $action_timestamp The optional timestamp, where the action happened. Useful if the action happened in the past, and the log entry is created afterwards.
     * @param bool  $delete_lot_if_empty If true, the part lot will be deleted if the amount is 0 after the withdrawal.
     */
    public function move(PartLot $origin, PartLot $target, float $amount, ?string $comment = null, ?\DateTimeInterface $action_timestamp = null, bool $delete_lot_if_empty = false): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $part = $origin->getPart();

        //Ensure that both part lots belong to the same part
        if($origin->getPart() !== $target->getPart()) {
            throw new \RuntimeException("Cannot move instock between different parts!");
        }

        //Check whether we have to round the amount
        if (!$part->useFloatAmount()) {
            $amount = round($amount);
        }

        //Ensure that we can withdraw from origin and add to target
        if (!$this->canWithdraw($origin) || !$this->canAdd($target)) {
            throw new \RuntimeException("Cannot move instock between these part lots!");
        }

        //Ensure that there is enough stock to withdraw
        if ($amount > $origin->getAmount()) {
            throw new \RuntimeException('Not enough stock to withdraw!');
        }

        $oldOriginAmount = $origin->getAmount();

        //Subtract the amount from the part lot
        $origin->setAmount($origin->getAmount() - $amount);
        //And add it to the target
        $target->setAmount($target->getAmount() + $amount);

        $event = PartStockChangedLogEntry::move($origin, $oldOriginAmount, $origin->getAmount(), $part->getAmountSum() , $comment, $target, $action_timestamp);
        $this->eventLogger->log($event);

        //Apply the comment also to global events, so it gets associated with the elementChanged log entry
        if (!$this->eventCommentHelper->isMessageSet() && ($comment !== null && $comment !== '')) {
            $this->eventCommentHelper->setMessage($comment);
        }

        if ($delete_lot_if_empty && $origin->getAmount() === 0.0) {
            $this->entityManager->remove($origin);
        }
    }
}
