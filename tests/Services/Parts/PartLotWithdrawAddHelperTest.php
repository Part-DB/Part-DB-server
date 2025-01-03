<?php

declare(strict_types=1);

namespace App\Tests\Services\Parts;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Services\Parts\PartLotWithdrawAddHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TestPartLot extends PartLot
{
    public function getID(): ?int
    {
        return 2;
    }
}

class PartLotWithdrawAddHelperTest extends WebTestCase
{

    /**
     * @var PartLotWithdrawAddHelper
     */
    protected $service;

    /** @var Part */
    private Part $part;

    /** @var StorageLocation */
    private StorageLocation $storageLocation;
    /** @var StorageLocation */
    private StorageLocation $full_storageLocation;

    /** @var PartLot */
    private PartLot $partLot1;
    /** @var PartLot */
    private PartLot $partLot2;
    /** @var PartLot */
    private PartLot $partLot3;

    /** @var PartLot */
    private PartLot $fullLot;
    /** @var PartLot */
    private PartLot $lotWithUnknownInstock;

    protected function setUp(): void
    {
        //Get a service instance.
        self::bootKernel();
        $this->service = self::getContainer()->get(PartLotWithdrawAddHelper::class);

        $this->fillTestData();
    }

    private function fillTestData(): void
    {
        $this->part = new Part();

        $this->storageLocation = new StorageLocation();
        $this->full_storageLocation = new StorageLocation();
        $this->full_storageLocation->setIsFull(true);

        $this->partLot1 = new TestPartLot();
        $this->partLot1->setPart($this->part);
        $this->partLot1->setAmount(10);

        $this->partLot2 = new TestPartLot();
        $this->partLot2->setPart($this->part);
        $this->partLot2->setStorageLocation($this->storageLocation);
        $this->partLot2->setAmount(2);

        $this->partLot3 = new TestPartLot();
        $this->partLot3->setPart($this->part);
        $this->partLot3->setAmount(0);

        $this->fullLot = new TestPartLot();
        $this->fullLot->setPart($this->part);
        $this->fullLot->setAmount(45);
        $this->fullLot->setStorageLocation($this->full_storageLocation);

        $this->lotWithUnknownInstock = new TestPartLot();
        $this->lotWithUnknownInstock->setPart($this->part);
        $this->lotWithUnknownInstock->setAmount(5);
        $this->lotWithUnknownInstock->setInstockUnknown(true);
        $this->lotWithUnknownInstock->setStorageLocation($this->storageLocation);
    }

    public function testCanWithdraw(): void
    {
        //Normal lots should be withdrawable
        $this->assertTrue($this->service->canWithdraw($this->partLot1));
        $this->assertTrue($this->service->canWithdraw($this->partLot2));
        //Empty lots should not be withdrawable
        $this->assertFalse($this->service->canWithdraw($this->partLot3));

        //Full lots should be withdrawable
        $this->assertTrue($this->service->canWithdraw($this->fullLot));
        //Lots with unknown instock should not be withdrawable
        $this->assertFalse($this->service->canWithdraw($this->lotWithUnknownInstock));
    }

    public function testCanAdd(): void
    {
        //Normal lots should be addable
        $this->assertTrue($this->service->canAdd($this->partLot1));
        $this->assertTrue($this->service->canAdd($this->partLot2));
        $this->assertTrue($this->service->canAdd($this->partLot3));

        //Full lots should not be addable
        $this->assertFalse($this->service->canAdd($this->fullLot));
        //Lots with unknown instock should not be addable
        $this->assertFalse($this->service->canAdd($this->lotWithUnknownInstock));
    }

    public function testAdd(): void
    {
        //Add 5 to lot 1
        $this->service->add($this->partLot1, 5, "Test");
        $this->assertEqualsWithDelta(15.0, $this->partLot1->getAmount(), PHP_FLOAT_EPSILON);

        //Add 3.2 to lot 2
        $this->service->add($this->partLot2, 3.2, "Test");
        $this->assertEqualsWithDelta(5.0, $this->partLot2->getAmount(), PHP_FLOAT_EPSILON);

        //Add 1.5 to lot 3
        $this->service->add($this->partLot3, 1.5, "Test");
        $this->assertEqualsWithDelta(2.0, $this->partLot3->getAmount(), PHP_FLOAT_EPSILON);

    }

    public function testWithdraw(): void
    {
        //Withdraw 5 from lot 1
        $this->service->withdraw($this->partLot1, 5, "Test");
        $this->assertEqualsWithDelta(5.0, $this->partLot1->getAmount(), PHP_FLOAT_EPSILON);

        //Withdraw 2.2 from lot 2
        $this->service->withdraw($this->partLot2, 2.2, "Test");
        $this->assertEqualsWithDelta(0.0, $this->partLot2->getAmount(), PHP_FLOAT_EPSILON);
    }

    public function testMove(): void
    {
        //Move 5 from lot 1 to lot 2
        $this->service->move($this->partLot1, $this->partLot2, 5, "Test");
        $this->assertEqualsWithDelta(5.0, $this->partLot1->getAmount(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(7.0, $this->partLot2->getAmount(), PHP_FLOAT_EPSILON);

        //Move 2.2 from lot 2 to lot 3
        $this->service->move($this->partLot2, $this->partLot3, 2.2, "Test");
        $this->assertEqualsWithDelta(5.0, $this->partLot2->getAmount(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(2.0, $this->partLot3->getAmount(), PHP_FLOAT_EPSILON);
    }
}
