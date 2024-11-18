<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Exceptions;

use App\Exceptions\TwigModeException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Error\Error;

class TwigModeExceptionTest extends KernelTestCase
{

    private string $projectPath;

    public function setUp(): void
    {
        self::bootKernel();

        $this->projectPath = self::getContainer()->getParameter('kernel.project_dir');
    }

    public function testGetSafeMessage(): void
    {
        $testException = new Error("Error at : " . $this->projectPath . "/src/dir/path/file.php");

        $twigModeException = new TwigModeException($testException);

        $this->assertSame("Error at : " . $this->projectPath . "/src/dir/path/file.php", $testException->getMessage());
        $this->assertSame("Error at : [Part-DB Root Folder]/src/dir/path/file.php", $twigModeException->getSafeMessage());
    }
}
