<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\EnvVarProcessors;

use App\EnvVarProcessors\CustomEnvVarProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;

final class CustomEnvVarProcessorTest extends TestCase
{
    protected CustomEnvVarProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new CustomEnvVarProcessor();
    }

    public function testGetProvidedTypes(): void
    {
        $this->assertSame(['validMailDSN' => 'bool'], CustomEnvVarProcessor::getProvidedTypes());
    }

    public function testValidMailDSNReturnsFalseForEmptyString(): void
    {
        $getEnv = fn () => '';

        $this->assertFalse($this->processor->getEnv('validMailDSN', 'MAILER_DSN', $getEnv));
    }

    public function testValidMailDSNReturnsFalseForNullDSN(): void
    {
        $getEnv = fn () => 'null://null';

        $this->assertFalse($this->processor->getEnv('validMailDSN', 'MAILER_DSN', $getEnv));
    }

    public function testValidMailDSNReturnsTrueForValidDSN(): void
    {
        $getEnv = fn () => 'smtp://user:pass@smtp.example.com:25';

        $this->assertTrue($this->processor->getEnv('validMailDSN', 'MAILER_DSN', $getEnv));
    }

    public function testValidMailDSNReturnsFalseWhenEnvNotFound(): void
    {
        $getEnv = function () {
            throw new EnvNotFoundException('MAILER_DSN');
        };

        $this->assertFalse($this->processor->getEnv('validMailDSN', 'MAILER_DSN', $getEnv));
    }

    public function testUnknownPrefixReturnsFalse(): void
    {
        $getEnv = fn () => 'some-value';

        $this->assertFalse($this->processor->getEnv('unknownPrefix', 'SOME_VAR', $getEnv));
    }
}
