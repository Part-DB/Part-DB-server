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
namespace App\Tests\Services\EntityMergers\Mergers;

use App\Entity\Parts\Part;
use App\Services\EntityMergers\Mergers\EntityMergerHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class EntityMergerHelperTraitTest extends KernelTestCase
{
    use EntityMergerHelperTrait;

    public function setUp(): void
    {
        self::bootKernel();
        $this->property_accessor = self::getContainer()->get(PropertyAccessorInterface::class);
    }

    public function testUseCallback(): void
    {
        $obj1 = new MergeTestClass();
        $obj1->non_nullable_string = 'obj1';
        $obj2 = new MergeTestClass();
        $obj2->non_nullable_string = 'obj2';

        $tmp = $this->useCallback(function ($target_value, $other_value, $target, $other, $field) use ($obj1, $obj2) {
            $this->assertSame($obj1, $target);
            $this->assertSame($obj2, $other);
            $this->assertSame('non_nullable_string', $field);
            $this->assertSame('obj1', $target_value);
            $this->assertSame('obj2', $other_value);

            return 'callback';

        }, $obj1, $obj2, 'non_nullable_string');

        //merge should return the target object
        $this->assertSame($obj1, $tmp);
        //And it should have the value from the callback set
        $this->assertSame('callback', $obj1->non_nullable_string);
    }

    public function testOtherFunctionIfNotNull(): void
    {
        $obj1 = new MergeTestClass();
        $obj1->string_property = null;
        $obj2 = new MergeTestClass();
        $obj2->string_property = 'obj2';

        $tmp = $this->useOtherValueIfNotNull($obj1, $obj2, 'string_property');
        $this->assertSame($obj1, $tmp);
        $this->assertSame('obj2', $obj1->string_property);

        $obj1->string_property = 'obj1';
        $tmp = $this->useOtherValueIfNotNull($obj1, $obj2, 'string_property');
        $this->assertSame($obj1, $tmp);
        $this->assertSame('obj1', $tmp->string_property);

        $obj1->string_property = null;
        $obj2->string_property = null;
        $this->assertSame($obj1, $this->useOtherValueIfNotNull($obj1, $obj2, 'string_property'));
        $this->assertNull($obj1->string_property);
    }

    public function testOtherFunctionIfNotEmpty(): void
    {
        $obj1 = new MergeTestClass();
        $obj1->string_property = null;
        $obj2 = new MergeTestClass();
        $obj2->string_property = 'obj2';

        $tmp = $this->useOtherValueIfNotEmtpy($obj1, $obj2, 'string_property');
        $this->assertSame($obj1, $tmp);
        $this->assertSame('obj2', $obj1->string_property);

        $obj1->string_property = 'obj1';
        $tmp = $this->useOtherValueIfNotEmtpy($obj1, $obj2, 'string_property');
        $this->assertSame($obj1, $tmp);
        $this->assertSame('obj1', $tmp->string_property);

        $obj1->string_property = null;
        $obj2->string_property = null;
        $this->assertSame($obj1, $this->useOtherValueIfNotEmtpy($obj1, $obj2, 'string_property'));
        $this->assertNull($obj1->string_property);

        $obj1->string_property = '';
        $obj2->string_property = 'test';
        $this->assertSame($obj1, $this->useOtherValueIfNotEmtpy($obj1, $obj2, 'string_property'));
        $this->assertSame('test', $obj1->string_property);
    }

    public function testUseLargerValue(): void
    {
        $obj1 = new MergeTestClass();
        $obj1->int_property = 1;
        $obj2 = new MergeTestClass();
        $obj2->int_property = 2;

        $tmp = $this->useLargerValue($obj1, $obj2, 'int_property');
        $this->assertSame($obj1, $tmp);
        $this->assertSame(2, $obj1->int_property);

        $obj1->int_property = 3;
        $obj2->int_property = 2;

        $tmp = $this->useLargerValue($obj1, $obj2, 'int_property');
        $this->assertSame($obj1, $tmp);
        $this->assertSame(3, $obj1->int_property);
    }

    public function testUseSmallerValue(): void
    {
        $obj1 = new MergeTestClass();
        $obj1->int_property = 1;
        $obj2 = new MergeTestClass();
        $obj2->int_property = 2;

        $tmp = $this->useSmallerValue($obj1, $obj2, 'int_property');
        $this->assertSame($obj1, $tmp);
        $this->assertSame(1, $obj1->int_property);

        $obj1->int_property = 3;
        $obj2->int_property = 2;

        $tmp = $this->useSmallerValue($obj1, $obj2, 'int_property');
        $this->assertSame($obj1, $tmp);
        $this->assertSame(2, $obj1->int_property);
    }

    public function testUseTrueValue(): void
    {
        $obj1 = new MergeTestClass();
        $obj1->bool_property = false;
        $obj2 = new MergeTestClass();
        $obj2->bool_property = true;

        $tmp = $this->useTrueValue($obj1, $obj2, 'bool_property');
        $this->assertSame($obj1, $tmp);
        $this->assertTrue($obj1->bool_property);

        $obj1->bool_property = true;
        $obj2->bool_property = false;
        $this->assertTrue($this->useTrueValue($obj1, $obj2, 'bool_property')->bool_property);

        $obj1->bool_property = false;
        $obj2->bool_property = false;
        $this->assertFalse($this->useTrueValue($obj1, $obj2, 'bool_property')->bool_property);
    }

    public function testMergeTags(): void
    {
        $obj1 = new MergeTestClass();
        $obj1->string_property = 'tag1,tag2,tag3';
        $obj2 = new MergeTestClass();
        $obj2->string_property = 'tag2,tag3,tag4';

        $tmp = $this->mergeTags($obj1, $obj2, 'string_property');
        $this->assertSame($obj1, $tmp);
        $this->assertSame('tag1,tag2,tag3,tag4', $obj1->string_property);
    }

    public function testAreStringsEqual(): void
    {
        $this->assertTrue($this->areStringsEqual('test', 'test'));
        $this->assertTrue($this->areStringsEqual('test', 'TEST'));
        $this->assertTrue($this->areStringsEqual('test', 'Test'));
        $this->assertTrue($this->areStringsEqual('test', ' Test '));
        $this->assertTrue($this->areStringsEqual('Test ', 'test'));

        $this->assertFalse($this->areStringsEqual('test', 'test2'));
        $this->assertFalse($this->areStringsEqual('test', 'test 1'));
    }

    public function testMergeTextWithSeparator(): void
    {
        $obj1 = new MergeTestClass();
        $obj1->string_property = 'Test1';
        $obj2 = new MergeTestClass();
        $obj2->string_property = 'Test2';

        $tmp = $this->mergeTextWithSeparator($obj1, $obj2, 'string_property', ' # ');
        $this->assertSame($obj1, $tmp);
        $this->assertSame('Test1 # Test2', $obj1->string_property);

        //If thee text is the same, it should not be duplicated
        $obj1->string_property = 'Test1';
        $obj2->string_property = 'Test1';
        $this->assertSame($obj1, $this->mergeTextWithSeparator($obj1, $obj2, 'string_property', ' # '));
        $this->assertSame('Test1', $obj1->string_property);

        //Test what happens if the second text is empty
        $obj1->string_property = 'Test1';
        $obj2->string_property = '';
        $this->assertSame($obj1, $this->mergeTextWithSeparator($obj1, $obj2, 'string_property', ' # '));
        $this->assertSame('Test1', $obj1->string_property);

    }

    public function testMergeComment(): void
    {
        $obj1 = new Part();
        $obj1->setName('Test1');
        $obj1->setComment('Comment1');
        $obj2 = new Part();
        $obj2->setName('Test2');
        $obj2->setComment('Comment2');

        $tmp = $this->mergeComment($obj1, $obj2);
        $this->assertSame($obj1, $tmp);
        $this->assertSame("Comment1\n\n<b>Test2:</b>\nComment2", $obj1->getComment());

        //If the comment is the same, it should not be duplicated
        $obj1->setComment('Comment1');
        $obj2->setComment('Comment1');
        $this->assertSame($obj1, $this->mergeComment($obj1, $obj2));
        $this->assertSame('Comment1', $obj1->getComment());

        //Test what happens if the second comment is empty
        $obj1->setComment('Comment1');
        $obj2->setComment('');
        $this->assertSame($obj1, $this->mergeComment($obj1, $obj2));
        $this->assertSame('Comment1', $obj1->getComment());
    }
}
