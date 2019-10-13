<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 *
 */

namespace App\Tests\Helpers;


use App\Helpers\BBCodeToMarkdownConverter;
use PHPUnit\Framework\TestCase;

class BBCodeToMarkdownConverterTest extends TestCase
{
    protected $converter;

    public function setUp()
    {
        $this->converter = new BBCodeToMarkdownConverter();
    }

    public function dataProvider()
    {
        return [
            ['[b]Bold[/b]', '**Bold**'],
            ['[i]Italic[/i]', '*Italic*'],
            ['[s]Strike[/s]', '<s>Strike</s>'],
            ['[url]https://foo.bar[/url]', '<https://foo.bar>'],
            ['[url=https://foo.bar]test[/url]', '[test](https://foo.bar)'],
            ['[center]Centered[/center]', '<div style="text-align:center">Centered</div>'],
            ['test no change', 'test no change'],
        ];
    }

    /**
     * @dataProvider dataProvider
     * @param $bbcode
     * @param $expected
     */
    public function testConvert($bbcode, $expected)
    {
        $this->assertEquals($expected, $this->converter->convert($bbcode));
    }
}