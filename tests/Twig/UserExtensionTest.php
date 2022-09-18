<?php

namespace App\Tests\Twig;

use App\Twig\UserExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserExtensionTest extends WebTestCase
{
    protected $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(UserExtension::class);
    }

    public function removeeLocaleFromPathDataSet()
    {
        yield ['/', '/de/'];
        yield ['/test', '/de/test'];
        yield ['/test/foo', '/en/test/foo'];
        yield ['/test/foo/bar?param1=val1&param2=val2', '/en/test/foo/bar?param1=val1&param2=val2'];
    }

    /**
     * @dataProvider removeeLocaleFromPathDataSet
     * @param  string  $expected
     * @param  string  $input
     * @return void
     */
    public function testRemoveLocaleFromPath(string $expected, string $input): void
    {
        $this->assertEquals($expected, $this->service->removeLocaleFromPath($input));
    }

    public function testRemoveLocaleFromPathException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->removeLocaleFromPath('/part/info/1');
    }
}
