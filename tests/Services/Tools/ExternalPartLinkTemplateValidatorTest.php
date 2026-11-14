<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);

namespace App\Tests\Services\Tools;

use App\Services\Tools\ExternalPartLinkTemplateValidator;
use PHPUnit\Framework\TestCase;

final class ExternalPartLinkTemplateValidatorTest extends TestCase
{
    public function testValidTemplateWithAllowedPlaceholdersPasses(): void
    {
        self::assertNull(ExternalPartLinkTemplateValidator::validate('https://example.com/{mpn}/{manufacturer}/{ipn}/{name}'));
    }

    public function testTemplateWithThePartsOwnDataPlaceholdersPasses(): void
    {
        self::assertNull(ExternalPartLinkTemplateValidator::validate('https://example.com/{category}/{footprint}/{id}'));
    }

    public function testValidTemplateWithoutPlaceholdersPasses(): void
    {
        self::assertNull(ExternalPartLinkTemplateValidator::validate('https://example.com/static'));
    }

    public function testUnknownPlaceholderIsRejected(): void
    {
        self::assertSame('unknown_placeholder', ExternalPartLinkTemplateValidator::validate('https://example.com/{sku}'));
        self::assertSame('sku', ExternalPartLinkTemplateValidator::findUnknownPlaceholder('https://example.com/{sku}'));
    }

    public function testNonHttpsSchemeIsRejected(): void
    {
        self::assertSame('invalid_template', ExternalPartLinkTemplateValidator::validate('http://example.com/{mpn}'));
    }

    public function testJavascriptSchemeIsRejected(): void
    {
        self::assertSame('invalid_template', ExternalPartLinkTemplateValidator::validate('javascript:alert(1)'));
    }

    public function testRelativeUrlIsRejected(): void
    {
        self::assertSame('invalid_template', ExternalPartLinkTemplateValidator::validate('/relative/{mpn}'));
    }
}
