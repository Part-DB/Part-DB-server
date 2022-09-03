<?php

namespace App\Tests\DataTables\Filters\Constraints;

use App\DataTables\Filters\Constraints\FilterTrait;
use App\Entity\Parts\MeasurementUnit;
use PHPUnit\Framework\TestCase;

class FilterTraitTest extends TestCase
{
    use FilterTrait;

    public function testUseHaving(): void
    {
        $this->assertFalse($this->useHaving);

        $this->useHaving();
        $this->assertTrue($this->useHaving);

        $this->useHaving(false);
        $this->assertFalse($this->useHaving);
    }

    public function isAggregateFunctionStringDataProvider(): iterable
    {
        yield [false, 'parts.test'];
        yield [false, 'attachments.test'];
        yield [true, 'COUNT(attachments)'];
        yield [true, 'MAX(attachments.value)'];
    }

    /**
     * @dataProvider isAggregateFunctionStringDataProvider
     */
    public function testIsAggregateFunctionString(bool $expected, string $input): void
    {
        $this->assertEquals($expected, $this->isAggregateFunctionString($input));
    }

}
