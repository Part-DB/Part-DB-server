<?php

namespace App\Tests\Services\TFA;

use App\Services\TFA\BackupCodeGenerator;
use PHPUnit\Framework\TestCase;

class BackupCodeGeneratorTest extends TestCase
{
    /**
     * Test if an exception is thrown if you are using a too high code length
     */
    public function testLengthUpperLimit()
    {
        $this->expectException(\RuntimeException::class);
        new BackupCodeGenerator(33, 10);
    }

    /**
     * Test if an exception is thrown if you are using a too high code length
     */
    public function testLengthLowerLimit()
    {
        $this->expectException(\RuntimeException::class);
        new BackupCodeGenerator(4, 10);
    }


    public function codeLengthDataProvider()
    {
        return [[6], [8], [10], [16]];
    }

    /**
     * @dataProvider  codeLengthDataProvider
     */
    public function testGenerateSingleCode(int $code_length)
    {
        $generator = new BackupCodeGenerator($code_length, 10);
        $this->assertRegExp("/^([a-f0-9]){{$code_length}}\$/", $generator->generateSingleCode());
    }

    public function codeCountDataProvider()
    {
        return [[2], [8], [10]];
    }

    /**
     * @dataProvider codeCountDataProvider
     */
    public function testGenerateCodeSet(int $code_count)
    {
        $generator = new BackupCodeGenerator(8, $code_count);
        $this->assertCount($code_count, $generator->generateCodeSet());
    }
}
